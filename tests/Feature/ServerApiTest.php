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

    public function test_admin_can_create_a_server(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/servers', [
                'server_id' => 344845,
                'name' => 'DEV SERVER',
                'ip_address' => '198.13.62.143',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.server_id', 344845)
            ->assertJsonPath('data.name', 'DEV SERVER');

        $this->assertDatabaseHas('servers', [
            'server_id' => 344845,
            'name' => 'DEV SERVER',
            'ip_address' => '198.13.62.143',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_a_server(): void
    {
        $server = Server::factory()->create(['server_id' => 344845]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/servers/{$server->id}", [
                'name' => 'Updated server',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.server_id', 344845)
            ->assertJsonPath('data.name', 'Updated server')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_admin_can_delete_an_unused_server(): void
    {
        $server = Server::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/servers/{$server->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Server deleted successfully.');

        $this->assertDatabaseMissing('servers', ['id' => $server->id]);
    }

    public function test_server_with_deployment_history_must_be_deactivated_instead_of_deleted(): void
    {
        $server = Server::factory()->create(['server_id' => 344845]);
        Deployment::create([
            'user_id' => $this->admin->id,
            'server_id' => $server->server_id,
            'domain' => 'example.com',
            'version' => 'non-www',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/servers/{$server->id}")
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'A server with deployment history cannot be deleted. Deactivate it instead.'
            );

        $this->assertDatabaseHas('servers', ['id' => $server->id]);
    }

    public function test_regular_user_cannot_manage_servers(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/servers')
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/servers', [
                'server_id' => 344845,
                'name' => 'DEV SERVER',
                'ip_address' => '198.13.62.143',
            ])
            ->assertForbidden();
    }
}
