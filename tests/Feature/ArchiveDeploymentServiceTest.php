<?php

namespace Tests\Feature;

use App\Models\Deployment;
use App\Models\Server;
use App\Models\User;
use App\Services\Cloudflare\CloudflareWorkerService;
use App\Services\Deployment\ArchiveDeploymentService;
use App\Services\InternetArchive\InternetArchiveService;
use App\Services\RunCloud\RunCloudService;
use App\Services\Sftp\SftpArchiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ArchiveDeploymentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_runs_the_archive_deployment_and_records_completion(): void
    {
        $deployment = $this->deployment();
        $archiveService = Mockery::mock(InternetArchiveService::class);
        $archiveService->shouldReceive('downloadSnapshot')
            ->once()
            ->with('example.com', 'non-www', Mockery::type('string'))
            ->andReturnTrue();
        $runCloudService = Mockery::mock(RunCloudService::class);
        $runCloudService->shouldReceive('createEmptyWebApplication')
            ->once()
            ->with('example.com', 123)
            ->andReturn(['success' => true, 'web_app_id' => 501]);
        $cloudflareWorkerService = Mockery::mock(CloudflareWorkerService::class);
        $cloudflareWorkerService->shouldReceive('changeWorkers')
            ->once()
            ->with('example.com', 'non-www')
            ->andReturn(['routes' => []]);
        $sftpArchiveService = Mockery::mock(SftpArchiveService::class);
        $sftpArchiveService->shouldNotReceive('uploadAndExtract');

        $result = (new ArchiveDeploymentService(
            $archiveService,
            $runCloudService,
            $cloudflareWorkerService,
            $sftpArchiveService,
        ))->deploy($deployment);

        $this->assertSame('completed', $result->status->value);
        $this->assertSame('completed', $result->current_step);
        $this->assertDatabaseHas('deployment_logs', [
            'deployment_id' => $deployment->id,
            'step_name' => 'downloading_archive',
            'status' => 'in_progress',
        ]);
        $this->assertDatabaseHas('deployment_logs', [
            'deployment_id' => $deployment->id,
            'step_name' => 'configuring_cloudflare_workers',
            'status' => 'in_progress',
        ]);
        $this->assertDatabaseHas('deployment_logs', [
            'deployment_id' => $deployment->id,
            'step_name' => 'completed',
            'status' => 'success',
        ]);
    }

    public function test_it_records_a_safe_failure_when_a_provider_fails(): void
    {
        $deployment = $this->deployment();
        $archiveService = Mockery::mock(InternetArchiveService::class);
        $archiveService->shouldReceive('downloadSnapshot')->once()->andReturnTrue();
        $runCloudService = Mockery::mock(RunCloudService::class);
        $runCloudService->shouldReceive('createEmptyWebApplication')
            ->once()
            ->andThrow(new RuntimeException('Sensitive provider response.'));
        $cloudflareWorkerService = Mockery::mock(CloudflareWorkerService::class);
        $cloudflareWorkerService->shouldNotReceive('changeWorkers');
        $sftpArchiveService = Mockery::mock(SftpArchiveService::class);
        $sftpArchiveService->shouldNotReceive('uploadAndExtract');

        try {
            (new ArchiveDeploymentService(
                $archiveService,
                $runCloudService,
                $cloudflareWorkerService,
                $sftpArchiveService,
            ))->deploy($deployment);
            $this->fail('The provider exception was not rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Sensitive provider response.', $exception->getMessage());
        }

        $this->assertSame('failed', $deployment->fresh()->status->value);
        $this->assertDatabaseHas('deployment_logs', [
            'deployment_id' => $deployment->id,
            'step_name' => 'deployment_failed',
            'message' => 'Archived website deployment failed. Review the server logs for details.',
        ]);
        $this->assertDatabaseMissing('deployment_logs', [
            'deployment_id' => $deployment->id,
            'message' => 'Sensitive provider response.',
        ]);
    }

    public function test_it_does_not_complete_when_cloudflare_worker_setup_fails(): void
    {
        $deployment = $this->deployment(['version' => 'www']);
        $archiveService = Mockery::mock(InternetArchiveService::class);
        $archiveService->shouldReceive('downloadSnapshot')
            ->once()
            ->with('example.com', 'www', Mockery::type('string'))
            ->andReturnTrue();
        $runCloudService = Mockery::mock(RunCloudService::class);
        $runCloudService->shouldReceive('createEmptyWebApplication')
            ->once()
            ->with('example.com', 123)
            ->andReturn(['success' => true, 'web_app_id' => 501]);
        $cloudflareWorkerService = Mockery::mock(CloudflareWorkerService::class);
        $cloudflareWorkerService->shouldReceive('changeWorkers')
            ->once()
            ->with('example.com', 'www')
            ->andThrow(new RuntimeException('Sensitive Cloudflare response.'));
        $sftpArchiveService = Mockery::mock(SftpArchiveService::class);
        $sftpArchiveService->shouldNotReceive('uploadAndExtract');

        try {
            (new ArchiveDeploymentService(
                $archiveService,
                $runCloudService,
                $cloudflareWorkerService,
                $sftpArchiveService,
            ))->deploy($deployment);
            $this->fail('The Cloudflare exception was not rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Sensitive Cloudflare response.', $exception->getMessage());
        }

        $this->assertSame('failed', $deployment->fresh()->status->value);
        $this->assertSame('deployment_failed', $deployment->fresh()->current_step);
        $this->assertDatabaseHas('deployment_logs', [
            'deployment_id' => $deployment->id,
            'step_name' => 'configuring_cloudflare_workers',
            'status' => 'in_progress',
        ]);
        $this->assertDatabaseMissing('deployment_logs', [
            'deployment_id' => $deployment->id,
            'step_name' => 'completed',
        ]);
        $this->assertDatabaseMissing('deployment_logs', [
            'deployment_id' => $deployment->id,
            'message' => 'Sensitive Cloudflare response.',
        ]);
    }

    public function test_it_uses_a_privately_uploaded_zip_without_downloading_it(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('deployments/1/archive.zip', 'zip contents');
        Server::factory()->create([
            'server_id' => 123,
            'ip_address' => '192.0.2.10',
        ]);
        $deployment = $this->deployment([
            'id' => 1,
            'archive_source' => 'upload',
            'archive_url' => null,
            'archive_path' => 'deployments/1/archive.zip',
        ]);
        $archiveService = Mockery::mock(InternetArchiveService::class);
        $archiveService->shouldNotReceive('downloadSnapshot');
        $runCloudService = Mockery::mock(RunCloudService::class);
        $runCloudService->shouldReceive('createEmptyWebApplication')
            ->once()
            ->with('example.com', 123, '192.0.2.10')
            ->andReturn([
                'success' => true,
                'web_app_id' => 501,
                'web_app_name' => 'example',
            ]);
        $cloudflareWorkerService = Mockery::mock(CloudflareWorkerService::class);
        $cloudflareWorkerService->shouldReceive('changeWorkers')
            ->once()
            ->with('example.com', 'non-www')
            ->andReturn(['routes' => []]);
        $sftpArchiveService = Mockery::mock(SftpArchiveService::class);
        $sftpArchiveService->shouldReceive('uploadAndExtract')
            ->once()
            ->with(
                '192.0.2.10',
                Mockery::on(fn (string $path): bool => str_ends_with(
                    str_replace('\\', '/', $path),
                    '/deployments/1/archive.zip',
                )),
                '/home/runcloud/webapps/example/archive-1.zip',
                '/home/runcloud/webapps/example/public_html',
                true,
                true,
            )
            ->andReturn([
                'entries_extracted' => 3,
                'archive_deleted' => true,
                'archive_uploaded' => true,
                'archive_path' => '/home/runcloud/webapps/example/archive-1.zip',
                'destination_path' => '/home/runcloud/webapps/example/public_html',
                'deleted_index_files' => ['/home/runcloud/webapps/example/public_html/index.html'],
            ]);

        $result = (new ArchiveDeploymentService(
            $archiveService,
            $runCloudService,
            $cloudflareWorkerService,
            $sftpArchiveService,
        ))->deploy($deployment);

        $this->assertSame('completed', $result->status->value);
        $this->assertDatabaseHas('deployment_logs', [
            'deployment_id' => $deployment->id,
            'step_name' => 'preparing_archive',
        ]);
    }

    public function test_uploaded_deployment_retry_reuses_the_web_app_created_by_an_earlier_attempt(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('deployments/1/archive.zip', 'zip contents');
        Server::factory()->create([
            'server_id' => 123,
            'ip_address' => '192.0.2.10',
        ]);
        $deployment = $this->deployment([
            'id' => 1,
            'archive_source' => 'upload',
            'archive_url' => null,
            'archive_path' => 'deployments/1/archive.zip',
            'status' => 'failed',
            'current_step' => 'deployment_failed',
        ]);
        $deployment->logs()->create([
            'step_name' => 'uploading_and_extracting_archive',
            'status' => 'in_progress',
            'message' => 'Previous attempt reached the SFTP step.',
        ]);

        $archiveService = Mockery::mock(InternetArchiveService::class);
        $archiveService->shouldNotReceive('downloadSnapshot');
        $runCloudService = Mockery::mock(RunCloudService::class);
        $runCloudService->shouldReceive('createEmptyWebApplication')
            ->once()
            ->with('example.com', 123, '192.0.2.10')
            ->andReturn(['already_exists' => true]);
        $runCloudService->shouldReceive('generateWebAppName')
            ->once()
            ->with('example.com')
            ->andReturn('example');
        $cloudflareWorkerService = Mockery::mock(CloudflareWorkerService::class);
        $cloudflareWorkerService->shouldReceive('changeWorkers')
            ->once()
            ->with('example.com', 'non-www')
            ->andReturn(['routes' => []]);
        $sftpArchiveService = Mockery::mock(SftpArchiveService::class);
        $sftpArchiveService->shouldReceive('uploadAndExtract')
            ->once()
            ->andReturn([
                'entries_extracted' => 3,
                'archive_deleted' => true,
                'archive_uploaded' => true,
                'archive_path' => '/home/runcloud/webapps/example/archive-1.zip',
                'destination_path' => '/home/runcloud/webapps/example/public_html',
                'deleted_index_files' => [],
            ]);

        $result = (new ArchiveDeploymentService(
            $archiveService,
            $runCloudService,
            $cloudflareWorkerService,
            $sftpArchiveService,
        ))->deploy($deployment);

        $this->assertSame('completed', $result->status->value);
        $this->assertDatabaseHas('deployment_logs', [
            'deployment_id' => $deployment->id,
            'step_name' => 'provisioning_web_app',
            'status' => 'success',
            'message' => 'Reusing the RunCloud web application created by an earlier deployment attempt.',
        ]);
    }

    public function test_it_does_not_process_a_cancelled_deployment(): void
    {
        $deployment = $this->deployment(['status' => 'cancelled', 'current_step' => 'cancelled']);
        $archiveService = Mockery::mock(InternetArchiveService::class);
        $archiveService->shouldNotReceive('downloadSnapshot');
        $runCloudService = Mockery::mock(RunCloudService::class);
        $runCloudService->shouldNotReceive('createEmptyWebApplication');
        $cloudflareWorkerService = Mockery::mock(CloudflareWorkerService::class);
        $cloudflareWorkerService->shouldNotReceive('changeWorkers');
        $sftpArchiveService = Mockery::mock(SftpArchiveService::class);
        $sftpArchiveService->shouldNotReceive('uploadAndExtract');

        $result = (new ArchiveDeploymentService(
            $archiveService,
            $runCloudService,
            $cloudflareWorkerService,
            $sftpArchiveService,
        ))->deploy($deployment);

        $this->assertSame('cancelled', $result->status->value);
        $this->assertDatabaseCount('deployment_logs', 0);
    }

    public function test_it_stops_between_provider_steps_when_the_deployment_is_cancelled(): void
    {
        $deployment = $this->deployment();
        $archiveService = Mockery::mock(InternetArchiveService::class);
        $archiveService->shouldReceive('downloadSnapshot')
            ->once()
            ->andReturnUsing(function () use ($deployment): bool {
                $deployment->update(['status' => 'cancelled', 'current_step' => 'cancelled']);

                return true;
            });
        $runCloudService = Mockery::mock(RunCloudService::class);
        $runCloudService->shouldNotReceive('createEmptyWebApplication');
        $cloudflareWorkerService = Mockery::mock(CloudflareWorkerService::class);
        $cloudflareWorkerService->shouldNotReceive('changeWorkers');
        $sftpArchiveService = Mockery::mock(SftpArchiveService::class);
        $sftpArchiveService->shouldNotReceive('uploadAndExtract');

        $result = (new ArchiveDeploymentService(
            $archiveService,
            $runCloudService,
            $cloudflareWorkerService,
            $sftpArchiveService,
        ))->deploy($deployment);

        $this->assertSame('cancelled', $result->status->value);
        $this->assertSame('cancelled', $result->current_step);
        $this->assertDatabaseMissing('deployment_logs', [
            'deployment_id' => $deployment->id,
            'step_name' => 'deployment_failed',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function deployment(array $attributes = []): Deployment
    {
        return Deployment::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'server_id' => 123,
            'domain' => 'example.com',
            'version' => 'non-www',
            'archive_url' => 'https://web.archive.org/download/example/example.zip',
            'archive_status' => 'ready',
            'status' => 'pending',
            'current_step' => 'archive_ready',
        ], $attributes));
    }
}
