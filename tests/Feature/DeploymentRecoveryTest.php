<?php

namespace Tests\Feature;

use App\Jobs\DeployArchivedWebsite;
use App\Jobs\WaitForWaybackArchive;
use App\Models\Deployment;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeploymentRecoveryTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('domainVersions')]
    public function test_admin_can_submit_recovery_for_domain_version_while_wayback_zip_is_in_progress(string $version): void
    {
        Queue::fake([WaitForWaybackArchive::class]);
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;
        Server::factory()->create(['server_id' => 123]);

        $response = $this->withToken($token)->postJson('/api/deployments', [
            'server_id' => 123,
            'domain' => 'example.com',
            'version' => $version,
            'archive_url' => 'https://web.archive.org/download/example/example.zip',
        ]);

        $response->assertAccepted()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.archive_status', 'waiting')
            ->assertJsonPath('data.server.server_id', 123)
            ->assertJsonPath('data.version', $version)
            ->assertJsonPath('data.current_step', 'waiting_for_archive')
            ->assertJsonPath('message', 'Recovery accepted and waiting for the Wayback ZIP.');

        $deploymentId = $response->json('data.id');

        $this->assertDatabaseHas('deployments', [
            'id' => $deploymentId,
            'user_id' => $admin->id,
            'version' => $version,
            'archive_status' => 'waiting',
            'current_step' => 'waiting_for_archive',
        ]);
        $this->assertDatabaseHas('deployment_logs', [
            'deployment_id' => $deploymentId,
            'step_name' => 'waiting_for_archive',
        ]);
        Queue::assertPushed(
            WaitForWaybackArchive::class,
            fn (WaitForWaybackArchive $job): bool => $job->deployment->getKey() === $deploymentId
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function domainVersions(): array
    {
        return [
            'www' => ['www'],
            'non-www' => ['non-www'],
        ];
    }

    public function test_returns_422_for_invalid_domain_version(): void
    {
        Queue::fake([WaitForWaybackArchive::class]);
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $this->withToken($token)->postJson('/api/deployments', [
            'server_id' => 123,
            'domain' => 'example.com',
            'version' => 'root',
            'archive_url' => 'https://web.archive.org/download/example/example.zip',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['version'])
            ->assertJsonPath('errors.version.0', 'The selected version is invalid.');

        $this->assertDatabaseCount('deployments', 0);
        Queue::assertNothingPushed();
    }

    public function test_returns_422_for_archive_url_outside_allowed_wayback_hosts(): void
    {
        Queue::fake([WaitForWaybackArchive::class]);
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $this->withToken($token)->postJson('/api/deployments', [
            'server_id' => 123,
            'domain' => 'example.com',
            'version' => 'non-www',
            'archive_url' => 'https://example.net/archive.zip',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['archive_url'])
            ->assertJsonPath('errors.archive_url.0', 'The archive URL must use an allowed Wayback Archive host.');

        $this->assertDatabaseCount('deployments', 0);
        Queue::assertNothingPushed();
    }

    public function test_regular_user_cannot_submit_recovery(): void
    {
        Queue::fake([WaitForWaybackArchive::class]);
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)->postJson('/api/deployments', [
            'server_id' => 123,
            'domain' => 'example.com',
            'version' => 'non-www',
            'archive_url' => 'https://web.archive.org/download/example/example.zip',
        ])->assertForbidden();

        $this->assertDatabaseCount('deployments', 0);
        Queue::assertNothingPushed();
    }

    public function test_recovery_requires_an_active_server_from_the_catalog(): void
    {
        Queue::fake([WaitForWaybackArchive::class]);
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;
        Server::factory()->create(['server_id' => 123, 'is_active' => false]);

        $this->withToken($token)->postJson('/api/deployments', [
            'server_id' => 123,
            'domain' => 'example.com',
            'version' => 'non-www',
            'archive_url' => 'https://web.archive.org/download/example/example.zip',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['server_id']);

        $this->assertDatabaseCount('deployments', 0);
        Queue::assertNothingPushed();
    }

    public function test_retry_returns_failed_recovery_to_archive_polling(): void
    {
        Queue::fake([WaitForWaybackArchive::class, DeployArchivedWebsite::class]);
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;
        $deployment = Deployment::create([
            'user_id' => $admin->id,
            'server_id' => 123,
            'domain' => 'example.com',
            'version' => 'non-www',
            'archive_url' => 'https://web.archive.org/download/example/example.zip',
            'archive_status' => 'failed',
            'status' => 'failed',
            'current_step' => 'archive_wait_failed',
        ]);

        $this->withToken($token)->postJson("/api/deployments/{$deployment->id}/retry")
            ->assertAccepted()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.archive_status', 'waiting')
            ->assertJsonPath('data.current_step', 'waiting_for_archive');

        $this->assertDatabaseHas('deployments', [
            'id' => $deployment->id,
            'archive_status' => 'waiting',
            'current_step' => 'waiting_for_archive',
        ]);
        Queue::assertPushed(WaitForWaybackArchive::class);
        Queue::assertNotPushed(DeployArchivedWebsite::class);
    }
}
