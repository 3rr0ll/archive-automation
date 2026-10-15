<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use App\Services\Sftp\SftpArchiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class RemoteArchiveApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_extract_a_remote_zip_archive(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $server = Server::factory()->create(['ip_address' => '192.0.2.10']);
        $service = Mockery::mock(SftpArchiveService::class);
        $service->shouldReceive('extract')
            ->once()
            ->with(
                '192.0.2.10',
                '/home/runcloud/archive.zip',
                '/home/runcloud/webapps/example/public_html',
                true,
                true,
            )
            ->andReturn([
                'archive_path' => '/home/runcloud/archive.zip',
                'destination_path' => '/home/runcloud/webapps/example/public_html',
                'entries_extracted' => 12,
                'archive_deleted' => true,
                'deleted_index_files' => [
                    '/home/runcloud/webapps/example/public_html/index.html',
                ],
            ]);
        $this->app->instance(SftpArchiveService::class, $service);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/servers/{$server->id}/archives/unzip", [
                'archive_path' => '/home/runcloud/archive.zip',
                'destination_path' => '/home/runcloud/webapps/example/public_html',
                'delete_archive' => true,
                'delete_existing_index' => true,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Remote ZIP archive extracted successfully.')
            ->assertJsonPath('data.entries_extracted', 12)
            ->assertJsonPath('data.archive_deleted', true)
            ->assertJsonPath(
                'data.deleted_index_files.0',
                '/home/runcloud/webapps/example/public_html/index.html',
            );
    }

    public function test_remote_archive_paths_cannot_traverse_parent_directories(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $server = Server::factory()->create();
        $service = Mockery::mock(SftpArchiveService::class);
        $service->shouldNotReceive('extract');
        $this->app->instance(SftpArchiveService::class, $service);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/servers/{$server->id}/archives/unzip", [
                'archive_path' => '/home/runcloud/../secrets.zip',
                'destination_path' => '/home/runcloud/public_html',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('archive_path');
    }

    public function test_regular_user_cannot_extract_a_remote_archive(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $server = Server::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/servers/{$server->id}/archives/unzip", [
                'archive_path' => '/home/runcloud/archive.zip',
                'destination_path' => '/home/runcloud/public_html',
            ])
            ->assertForbidden();
    }

    public function test_sftp_failure_returns_a_safe_error_response(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $server = Server::factory()->create();
        $service = Mockery::mock(SftpArchiveService::class);
        $service->shouldReceive('extract')
            ->once()
            ->andThrow(new RuntimeException('Sensitive SSH diagnostic.'));
        $this->app->instance(SftpArchiveService::class, $service);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/servers/{$server->id}/archives/unzip", [
                'archive_path' => '/home/runcloud/archive.zip',
                'destination_path' => '/home/runcloud/public_html',
            ])
            ->assertStatus(502)
            ->assertExactJson([
                'message' => 'The remote ZIP archive could not be extracted.',
            ]);
    }
}
