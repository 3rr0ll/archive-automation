<?php

namespace Tests\Feature;

use App\Jobs\DeployArchivedWebsite;
use App\Jobs\WaitForWaybackArchive;
use App\Models\Deployment;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminDeploymentManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_filter_search_and_sort_deployments(): void
    {
        $server = Server::factory()->create();
        $target = $this->deployment([
            'server_id' => $server->server_id,
            'domain' => 'target-example.com',
            'status' => 'completed',
            'archive_status' => 'ready',
            'current_step' => 'deployed',
        ]);
        $target->created_at = '2026-10-05 12:00:00';
        $target->save();
        $this->deployment([
            'server_id' => $server->server_id,
            'domain' => 'unrelated.test',
            'status' => 'failed',
            'archive_status' => 'failed',
            'current_step' => 'archive_wait_failed',
        ]);

        $query = http_build_query([
            'search' => 'target-example',
            'archive_status' => 'ready',
            'server_id' => $server->server_id,
            'date_from' => '2026-10-01',
            'date_to' => '2026-10-07',
            'current_step' => 'deployed',
            'sort_by' => 'domain',
            'sort_direction' => 'asc',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/deployments?'.$query)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $target->id);

        $this->assertStringContainsString('search=target-example', $response->json('links.first'));
    }

    public function test_admin_can_view_filtered_deployment_statistics(): void
    {
        $this->deployment(['domain' => 'selected.test', 'status' => 'pending', 'archive_status' => 'waiting', 'current_step' => 'queued']);
        $this->deployment(['domain' => 'selected.test', 'status' => 'processing', 'archive_status' => 'ready', 'current_step' => 'deploying']);
        $this->deployment(['domain' => 'selected.test', 'status' => 'failed', 'archive_status' => 'failed', 'current_step' => 'failed']);
        $this->deployment(['domain' => 'excluded.test', 'status' => 'completed', 'archive_status' => 'ready']);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/deployments/statistics?search=selected')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.active', 2)
            ->assertJsonPath('data.status_counts.pending', 1)
            ->assertJsonPath('data.status_counts.processing', 1)
            ->assertJsonPath('data.status_counts.failed', 1)
            ->assertJsonPath('data.status_counts.completed', 0)
            ->assertJsonPath('data.archive_status_counts.waiting', 1)
            ->assertJsonPath('data.archive_status_counts.ready', 1)
            ->assertJsonPath('data.archive_status_counts.failed', 1)
            ->assertJsonPath('data.current_step_counts.queued', 1);
    }

    public function test_admin_can_paginate_and_filter_deployment_logs(): void
    {
        $deployment = $this->deployment();
        $deployment->logs()->create(['step_name' => 'download', 'status' => 'success', 'message' => 'Downloaded.']);
        $firstError = $deployment->logs()->create(['step_name' => 'extract', 'status' => 'error', 'message' => 'First error.']);
        $latestError = $deployment->logs()->create(['step_name' => 'deploy', 'status' => 'error', 'message' => 'Latest error.']);
        $firstError->forceFill(['created_at' => now()->subMinute()])->save();
        $latestError->forceFill(['created_at' => now()])->save();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/deployments/{$deployment->id}/logs?status=error&per_page=1")
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('data.0.message', 'Latest error.');
    }

    public function test_admin_can_bulk_cancel_eligible_deployments(): void
    {
        $pending = $this->deployment(['status' => 'pending']);
        $processing = $this->deployment(['status' => 'processing']);
        $completed = $this->deployment(['status' => 'completed']);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/deployments/bulk-cancel', [
                'ids' => [$pending->id, $processing->id, $completed->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed_ids', [$pending->id, $processing->id])
            ->assertJsonPath('data.skipped.0.id', $completed->id);

        $this->assertSame('cancelled', $pending->fresh()->status->value);
        $this->assertSame('cancelled', $processing->fresh()->status->value);
        $this->assertSame('completed', $completed->fresh()->status->value);
        $this->assertDatabaseCount('deployment_logs', 2);
    }

    public function test_admin_can_bulk_retry_eligible_deployments(): void
    {
        Queue::fake();
        $failed = $this->deployment(['status' => 'failed']);
        $cancelledArchive = $this->deployment([
            'status' => 'cancelled',
            'archive_url' => 'https://archive.org/example.zip',
        ]);
        $pending = $this->deployment(['status' => 'pending']);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/deployments/bulk-retry', [
                'ids' => [$failed->id, $cancelledArchive->id, $pending->id],
            ])
            ->assertAccepted()
            ->assertJsonPath('data.processed_ids', [$failed->id, $cancelledArchive->id])
            ->assertJsonPath('data.skipped.0.id', $pending->id);

        Queue::assertPushed(DeployArchivedWebsite::class, fn ($job): bool => $job->deployment->is($failed));
        Queue::assertPushed(WaitForWaybackArchive::class, fn ($job): bool => $job->deployment->is($cancelledArchive));
        $this->assertSame('queued', $failed->fresh()->current_step);
        $this->assertSame('waiting_for_archive', $cancelledArchive->fresh()->current_step);
    }

    public function test_admin_can_bulk_delete_non_processing_deployments(): void
    {
        $failed = $this->deployment(['status' => 'failed']);
        $pending = $this->deployment(['status' => 'pending']);
        $processing = $this->deployment(['status' => 'processing']);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/deployments/bulk-delete', [
                'ids' => [$failed->id, $pending->id, $processing->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed_ids', [$failed->id, $pending->id])
            ->assertJsonPath('data.skipped.0.id', $processing->id);

        $this->assertDatabaseMissing('deployments', ['id' => $failed->id]);
        $this->assertDatabaseMissing('deployments', ['id' => $pending->id]);
        $this->assertDatabaseHas('deployments', ['id' => $processing->id]);
    }

    public function test_bulk_endpoints_validate_ids_and_require_an_admin(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/deployments/bulk-cancel', ['ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids');

        $user = User::factory()->create(['role' => 'user']);
        $deployment = $this->deployment();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/deployments/{$deployment->id}/logs")
            ->assertForbidden();
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/deployments/statistics')
            ->assertForbidden();
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/deployments/bulk-delete', ['ids' => [$deployment->id]])
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function deployment(array $attributes = []): Deployment
    {
        return Deployment::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'server_id' => 123,
            'domain' => fake()->unique()->domainName(),
            'version' => 'non-www',
            'archive_status' => 'waiting',
            'status' => 'pending',
            'current_step' => 'queued',
        ], $attributes));
    }
}
