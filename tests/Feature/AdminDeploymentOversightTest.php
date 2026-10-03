<?php

namespace Tests\Feature;

use App\Jobs\DeployArchivedWebsite;
use App\Models\Deployment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminDeploymentOversightTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->token = $admin->createToken('test-token')->plainTextToken;
    }

    private function deployment(User $user, string $status = 'pending'): Deployment
    {
        return Deployment::create([
            'user_id' => $user->id,
            'server_id' => 123,
            'domain' => fake()->unique()->domainName(),
            'version' => '8.4',
            'status' => $status,
            'current_step' => $status,
        ]);
    }

    public function test_admin_can_list_deployments_across_users(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $this->deployment($firstUser);
        $this->deployment($secondUser, 'failed');

        $this->withToken($this->token)->getJson('/api/deployments')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonFragment(['email' => $firstUser->email])
            ->assertJsonFragment(['email' => $secondUser->email]);
    }

    public function test_admin_can_view_any_deployment_with_logs(): void
    {
        $deployment = $this->deployment(User::factory()->create());
        $deployment->logs()->create([
            'step_name' => 'download',
            'status' => 'success',
            'message' => 'Archive downloaded.',
        ]);

        $this->withToken($this->token)->getJson("/api/deployments/{$deployment->id}")
            ->assertOk()
            ->assertJsonPath('data.user_id', $deployment->user_id)
            ->assertJsonPath('data.logs.0.message', 'Archive downloaded.');
    }

    public function test_admin_can_cancel_another_users_active_deployment(): void
    {
        $deployment = $this->deployment(User::factory()->create(), 'processing');

        $this->withToken($this->token)->postJson("/api/deployments/{$deployment->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('deployment_logs', [
            'deployment_id' => $deployment->id,
            'step_name' => 'cancelled',
        ]);
    }

    public function test_admin_can_retry_another_users_failed_deployment(): void
    {
        Queue::fake();
        $deployment = $this->deployment(User::factory()->create(), 'failed');

        $this->withToken($this->token)->postJson("/api/deployments/{$deployment->id}/retry")
            ->assertAccepted()
            ->assertJsonPath('data.status', 'pending');

        Queue::assertPushed(DeployArchivedWebsite::class, fn ($job) => $job->deployment->is($deployment));
    }

    public function test_admin_can_delete_another_users_inactive_deployment(): void
    {
        $deployment = $this->deployment(User::factory()->create(), 'failed');

        $this->withToken($this->token)->deleteJson("/api/deployments/{$deployment->id}")
            ->assertOk();

        $this->assertDatabaseMissing('deployments', ['id' => $deployment->id]);
    }

    public function test_regular_user_cannot_access_deployment_oversight(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)->getJson('/api/deployments')->assertForbidden();
    }
}
