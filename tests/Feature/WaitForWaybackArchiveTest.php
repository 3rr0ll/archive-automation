<?php

namespace Tests\Feature;

use App\Jobs\DeployArchivedWebsite;
use App\Jobs\WaitForWaybackArchive;
use App\Models\Deployment;
use App\Models\User;
use App\Services\InternetArchive\InternetArchiveService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WaitForWaybackArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_releases_job_without_deploying_when_zip_is_still_in_progress(): void
    {
        Http::preventStrayRequests();
        Queue::fake([DeployArchivedWebsite::class]);
        $archiveUrl = 'https://web.archive.org/download/example/example.zip';
        Http::fake([$archiveUrl => Http::response(status: 202)]);
        $deployment = $this->deployment($archiveUrl);
        $job = (new WaitForWaybackArchive($deployment))->withFakeQueueInteractions();

        $job->handle(app(InternetArchiveService::class));

        $job->assertReleased(60);
        $this->assertDatabaseHas('deployments', [
            'id' => $deployment->id,
            'archive_status' => 'waiting',
            'current_step' => 'waiting_for_archive',
        ]);
        Queue::assertNotPushed(DeployArchivedWebsite::class);
        Http::assertSentCount(1);
    }

    public function test_queues_deployment_once_when_zip_becomes_ready(): void
    {
        Http::preventStrayRequests();
        Queue::fake([DeployArchivedWebsite::class]);
        $archiveUrl = 'https://web.archive.org/download/example/example.zip';
        Http::fake([
            $archiveUrl => Http::response('', 200, ['Content-Type' => 'application/zip']),
        ]);
        $deployment = $this->deployment($archiveUrl);
        $job = new WaitForWaybackArchive($deployment);

        $job->handle(app(InternetArchiveService::class));
        $job->handle(app(InternetArchiveService::class));

        $this->assertDatabaseHas('deployments', [
            'id' => $deployment->id,
            'archive_status' => 'ready',
            'current_step' => 'archive_ready',
        ]);
        $this->assertDatabaseHas('deployment_logs', [
            'deployment_id' => $deployment->id,
            'step_name' => 'archive_ready',
        ]);
        Queue::assertPushedTimes(DeployArchivedWebsite::class, 1);
        Http::assertSentCount(1);
    }

    public function test_marks_recovery_failed_after_archive_polling_is_exhausted(): void
    {
        $deployment = $this->deployment('https://web.archive.org/download/example/example.zip');
        $job = new WaitForWaybackArchive($deployment);

        $job->failed(new Exception('Attempts exhausted.'));

        $this->assertDatabaseHas('deployments', [
            'id' => $deployment->id,
            'status' => 'failed',
            'archive_status' => 'failed',
            'current_step' => 'archive_wait_failed',
        ]);
        $this->assertDatabaseHas('deployment_logs', [
            'deployment_id' => $deployment->id,
            'step_name' => 'archive_wait_failed',
        ]);
    }

    private function deployment(string $archiveUrl): Deployment
    {
        $user = User::factory()->create();

        return Deployment::create([
            'user_id' => $user->id,
            'server_id' => 123,
            'domain' => 'example.com',
            'version' => 'non-www',
            'archive_url' => $archiveUrl,
            'archive_status' => 'waiting',
            'status' => 'pending',
            'current_step' => 'waiting_for_archive',
        ]);
    }
}
