<?php

namespace Tests\Feature;

use App\Jobs\DeployArchivedWebsite;
use App\Jobs\WaitForWaybackArchive;
use App\Models\Deployment;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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

    public function test_deleting_a_deployment_also_deletes_its_runcloud_web_application(): void
    {
        Http::fake([
            'https://manage.runcloud.io/api/v3/servers/123/webapps/501' => Http::response([], 200),
        ]);
        $deployment = $this->deployment([
            'status' => 'completed',
            'runcloud_web_app_id' => 501,
            'runcloud_web_app_name' => 'example',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/deployments/{$deployment->id}")
            ->assertOk();

        $this->assertDatabaseMissing('deployments', ['id' => $deployment->id]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://manage.runcloud.io/api/v3/servers/123/webapps/501');
    }

    public function test_local_deployment_is_kept_when_runcloud_deletion_fails(): void
    {
        Http::fake([
            'https://manage.runcloud.io/api/v3/servers/123/webapps/501' => Http::response([
                'message' => 'RunCloud is unavailable.',
            ], 503),
        ]);
        $deployment = $this->deployment([
            'status' => 'completed',
            'runcloud_web_app_id' => 501,
            'runcloud_web_app_name' => 'example',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/deployments/{$deployment->id}")
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The RunCloud web application could not be deleted, so the local deployment was kept. RunCloud API error (503): RunCloud is unavailable.',
            );

        $this->assertDatabaseHas('deployments', ['id' => $deployment->id]);
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
            ->assertOk();
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/deployments/statistics')
            ->assertForbidden();
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/deployments/bulk-delete', ['ids' => [$deployment->id]])
            ->assertForbidden();
    }

    public function test_admin_can_bulk_search_deployments_by_domains(): void
    {
        $dep1 = $this->deployment(['domain' => 'site-one.com']);
        $dep2 = $this->deployment(['domain' => 'site-two.com']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/deployments/bulk-search', [
                'domains' => ['site-one.com', 'site-two.com', 'not-exist.com'],
            ])
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $response['found']);
        $this->assertEquals(['not-exist.com'], $response['missing']);
    }

    public function test_admin_can_bulk_search_and_delete_runcloud_live_apps(): void
    {
        $server = Server::create([
            'server_id' => 999,
            'name' => 'Primary Server',
            'ip_address' => '1.2.3.4',
            'is_active' => true,
        ]);

        Http::fake([
            'https://manage.runcloud.io/api/v3/servers/999/webapps*' => Http::response([
                'data' => [
                    [
                        'id' => 888,
                        'name' => 'externaldomain',
                        'domainName' => 'external-domain.com',
                        'domains' => [['name' => 'external-domain.com']],
                    ],
                ],
            ], 200),
            'https://manage.runcloud.io/api/v3/servers/999/webapps/888' => Http::response([], 200),
        ]);

        // Search finds it on RunCloud
        $searchResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/deployments/bulk-search', [
                'domains' => ['external-domain.com'],
            ])
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $searchResponse['found']);
        $this->assertEquals('runcloud', $searchResponse['found'][0]['source']);
        $this->assertEquals('rc_999_888', $searchResponse['found'][0]['id']);

        // Delete removes it from RunCloud
        $deleteResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/deployments/bulk-delete', [
                'ids' => ['rc_999_888'],
            ])
            ->assertOk()
            ->json('data');

        $this->assertEquals(['rc_999_888'], $deleteResponse['processed_ids']);
    }

    public function test_admin_can_access_bulk_delete_page(): void
    {
        $this->actingAs($this->admin)
            ->get('/bulk-delete')
            ->assertOk()
            ->assertSee('Bulk Delete');

        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user)
            ->get('/bulk-delete')
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
