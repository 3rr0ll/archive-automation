<?php

namespace Tests\Feature;

use App\Models\Deployment;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class ServerApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_list_manually_configured_servers(): void
    {
        $server = Server::factory()->create([
            'server_id' => 344845,
            'name' => 'DEV SERVER',
            'ip_address' => '198.13.62.143',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/servers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $server->id)
            ->assertJsonPath('data.0.server_id', 344845)
            ->assertJsonPath('data.0.ip_address', '198.13.62.143');
    }

    public function test_admin_can_sync_servers_from_runcloud(): void
    {
        Http::fake([
            'https://manage.runcloud.io/api/v3/servers' => Http::response([
                'data' => [
                    [
                        'id' => 12345,
                        'name' => 'RUNCLOUD PRODUCTION',
                        'ipAddress' => '1.2.3.4',
                    ],
                    [
                        'id' => 67890,
                        'name' => 'RUNCLOUD STAGING',
                        'ipAddress' => '5.6.7.8',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/servers/sync');

        $response->assertOk()
            ->assertJsonPath('message', 'Servers synced successfully from RunCloud.')
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('servers', [
            'server_id' => 12345,
            'name' => 'RUNCLOUD PRODUCTION',
            'ip_address' => '1.2.3.4',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('servers', [
            'server_id' => 67890,
            'name' => 'RUNCLOUD STAGING',
            'ip_address' => '5.6.7.8',
            'is_active' => false,
        ]);
    }

    public function test_sync_preserves_the_status_of_an_existing_server(): void
    {
        Server::factory()->create([
            'server_id' => 12345,
            'name' => 'OLD NAME',
            'is_active' => true,
        ]);

        Http::fake([
            'https://manage.runcloud.io/api/v3/servers' => Http::response([
                'data' => [[
                    'id' => 12345,
                    'name' => 'UPDATED NAME',
                    'ipAddress' => '1.2.3.4',
                ]],
            ], 200),
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/servers/sync')
            ->assertOk();

        $this->assertDatabaseHas('servers', [
            'server_id' => 12345,
            'name' => 'UPDATED NAME',
            'is_active' => true,
        ]);
    }

    public function test_website_sync_imports_multiple_pages_and_skips_existing_apps_and_domains(): void
    {
        Sleep::fake();
        Http::preventStrayRequests();
        config(['services.runcloud.api_token' => 'test-token']);
        $server = Server::factory()->create(['server_id' => 12345, 'is_active' => false]);
        $existing = [
            'user_id' => $this->admin->id,
            'server_id' => $server->server_id,
            'version' => 'non-www',
            'status' => 'completed',
        ];
        $existingApp = Deployment::create([...$existing, 'domain' => 'old.example.com', 'runcloud_web_app_id' => 1]);
        Deployment::create([...$existing, 'domain' => 'WWW.EXISTING.TEST.', 'server_id' => null]);

        $baseUrl = 'https://manage.runcloud.io/api/v3/servers/12345/webapps';
        Http::fake([
            "{$baseUrl}?page=1" => Http::response([
                'data' => [
                    ['id' => 1, 'name' => 'renamed'],
                    ['id' => 2, 'name' => 'duplicate', 'domainName' => 'existing.test'],
                    ['id' => 3, 'name' => 'first'],
                    ['id' => 4, 'name' => 'empty'],
                    ['id' => 5, 'name' => 'next-in-page', 'domainName' => 'next-in-page.test'],
                ],
                'meta' => ['pagination' => ['total_pages' => 2]],
            ]),
            "{$baseUrl}?page=2" => Http::response([
                'data' => [['id' => 6, 'name' => 'last', 'primaryDomain' => ['name' => 'last.test']]],
                'meta' => ['pagination' => ['total_pages' => 2]],
            ]),
            "{$baseUrl}/3/domains" => Http::response(['data' => [
                ['name' => 'alias.test', 'type' => 'alias'],
                ['name' => 'www.first.test', 'type' => 'primary'],
            ]]),
            "{$baseUrl}/4/domains" => Http::response(['data' => []]),
        ]);

        $endpoint = "/api/servers/{$server->id}/sync-websites";
        $this->actingAs($this->admin, 'sanctum')->postJson($endpoint)
            ->assertOk()
            ->assertJsonPath('data', ['imported' => 1, 'existing' => 2, 'unavailable' => 1, 'next' => ['page' => 1, 'offset' => 4]]);
        $this->postJson($endpoint, ['page' => 1, 'offset' => 4])
            ->assertOk()
            ->assertJsonPath('data.next', ['page' => 2, 'offset' => 0]);
        $this->postJson($endpoint, ['page' => 2])
            ->assertOk()
            ->assertJsonPath('data', ['imported' => 1, 'existing' => 0, 'unavailable' => 0, 'next' => null]);
        $this->postJson($endpoint, ['page' => 2])
            ->assertOk()
            ->assertJsonPath('data.existing', 1);

        $this->assertDatabaseCount('deployments', 5);
        $this->assertDatabaseCount('deployment_logs', 3);
        $this->assertSame('old.example.com', $existingApp->fresh()->domain);
        $this->assertDatabaseHas('deployments', [
            'user_id' => $this->admin->id,
            'server_id' => $server->server_id,
            'runcloud_web_app_id' => 3,
            'runcloud_web_app_name' => 'first',
            'domain' => 'first.test',
            'version' => 'www',
            'archive_source' => 'runcloud',
            'status' => 'completed',
        ]);
        $this->actingAs($this->admin)->get('/dashboard')
            ->assertOk()
            ->assertViewHas('initialDeployments', fn (array $deployments): bool => collect($deployments)->contains(
                fn (Deployment $deployment): bool => $deployment->domain === 'first.test' && $deployment->server?->is($server)
            ));
    }

    public function test_website_sync_reports_a_failed_lookup_without_saving_the_batch(): void
    {
        Sleep::fake();
        Http::preventStrayRequests();
        config(['services.runcloud.api_token' => 'test-token']);
        $server = Server::factory()->create(['server_id' => 12345]);
        $baseUrl = 'https://manage.runcloud.io/api/v3/servers/12345/webapps';
        Http::fake([
            "{$baseUrl}?page=1" => Http::response(['data' => [
                ['id' => 1, 'name' => 'first', 'domainName' => 'first.test'],
                ['id' => 2, 'name' => 'second'],
            ]]),
            "{$baseUrl}/2/domains" => Http::response([], 503),
        ]);

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/servers/{$server->id}/sync-websites")
            ->assertStatus(502)
            ->assertJsonPath('message', 'Could not finish syncing websites. Try again shortly.');

        $this->assertDatabaseEmpty('deployments');
    }

    public function test_admin_can_toggle_server_active_state(): void
    {
        $server = Server::factory()->create([
            'server_id' => 344845,
            'is_active' => true,
        ]);

        // Toggle to inactive
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/servers/{$server->id}/toggle-active")
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('message', 'Server deactivated.');

        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'is_active' => false,
        ]);

        // Explicitly set to active
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/servers/{$server->id}/toggle-active", [
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('message', 'Server activated.');

        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'is_active' => true,
        ]);
    }

    public function test_activating_a_server_deactivates_the_previously_active_server(): void
    {
        $previouslyActive = Server::factory()->create(['is_active' => true]);
        $server = Server::factory()->create(['is_active' => false]);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/servers/{$server->id}/toggle-active", [
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertFalse($previouslyActive->fresh()->is_active);
        $this->assertTrue($server->fresh()->is_active);
        $this->assertSame(1, Server::query()->where('is_active', true)->count());
    }

    public function test_direct_create_or_delete_routes_are_not_available(): void
    {
        $server = Server::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/servers', ['name' => 'New Server'])
            ->assertStatus(405);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/servers/{$server->id}")
            ->assertStatus(405);
    }

    public function test_regular_user_can_list_only_active_servers(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $activeServer = Server::factory()->create(['is_active' => true]);
        Server::factory()->create(['is_active' => false]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/servers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeServer->id)
            ->assertJsonPath('data.0.is_active', true);
    }

    public function test_regular_user_cannot_manage_servers(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $server = Server::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/servers/sync')
            ->assertForbidden();

        $this->postJson("/api/servers/{$server->id}/sync-websites")
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/servers/{$server->id}/toggle-active")
            ->assertForbidden();
    }
}
