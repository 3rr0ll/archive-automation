<?php

namespace Tests\Feature;

use App\Models\Deployment;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        \Illuminate\Support\Facades\Http::fake([
            'https://manage.runcloud.io/api/v3/servers' => \Illuminate\Support\Facades\Http::response([
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
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('servers', [
            'server_id' => 67890,
            'name' => 'RUNCLOUD STAGING',
            'ip_address' => '5.6.7.8',
            'is_active' => true,
        ]);
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

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/servers/{$server->id}/toggle-active")
            ->assertForbidden();
    }
}
