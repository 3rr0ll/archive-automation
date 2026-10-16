<?php

namespace Tests\Feature;

use App\Models\ApiUsageRecord;
use App\Models\Deployment;
use App\Models\User;
use App\Services\ApiUsage\ApiUsageContext;
use App\Services\Http\ExternalHttpClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiUsageMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->token = $this->admin->createToken('test-token')->plainTextToken;
    }

    public function test_admin_can_view_provider_totals_across_all_deployments(): void
    {
        $deployment = $this->deployment();
        $this->usage($deployment, 'runcloud', true);
        $this->usage($deployment, 'runcloud', false);
        $this->usage($deployment, 'cloudflare', true);
        $this->usage(null, 'google_search_console', true);

        $this->withToken($this->token)
            ->getJson('/api/api-usage')
            ->assertOk()
            ->assertJsonPath('data.summary.requests', 4)
            ->assertJsonPath('data.summary.successful', 3)
            ->assertJsonPath('data.summary.failed', 1)
            ->assertJsonPath('data.summary.deployment_requests', 3)
            ->assertJsonPath('data.summary.unassigned_requests', 1)
            ->assertJsonFragment([
                'provider' => 'runcloud',
                'requests' => 2,
                'successful' => 1,
                'failed' => 1,
            ])
            ->assertJsonFragment([
                'provider' => 'cloudflare',
                'requests' => 1,
                'successful' => 1,
                'failed' => 0,
            ]);
    }

    public function test_admin_can_view_usage_for_one_deployment(): void
    {
        $deployment = $this->deployment();
        $otherDeployment = $this->deployment();
        $this->usage($deployment, 'cloudflare', true, 'POST /zones/dns_records');
        $this->usage($deployment, 'cloudflare', false, 'POST /zones/dns_records');
        $this->usage($otherDeployment, 'runcloud', true);

        $this->withToken($this->token)
            ->getJson("/api/deployments/{$deployment->id}/api-usage")
            ->assertOk()
            ->assertJsonPath('data.deployment_id', $deployment->id)
            ->assertJsonPath('data.summary.requests', 2)
            ->assertJsonPath('data.summary.providers.0.provider', 'cloudflare')
            ->assertJsonPath('data.operations.0.requests', 2);
    }

    public function test_tracked_laravel_http_request_is_linked_to_active_deployment(): void
    {
        Http::fake([
            'https://api.example.test/*' => Http::response(['ok' => true], 200),
        ]);
        $deployment = $this->deployment();

        app(ApiUsageContext::class)->forDeployment($deployment->id, function (): void {
            app(ExternalHttpClient::class)
                ->configure(Http::baseUrl('https://api.example.test'), 'runcloud')
                ->get('/servers?token=secret');
        });

        $this->assertDatabaseHas('api_usage_records', [
            'deployment_id' => $deployment->id,
            'provider' => 'runcloud',
            'method' => 'GET',
            'endpoint' => 'https://api.example.test/servers',
            'status_code' => 200,
            'successful' => true,
        ]);
    }

    public function test_regular_user_cannot_view_usage_totals(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->withToken($user->createToken('test-token')->plainTextToken)
            ->getJson('/api/api-usage')
            ->assertForbidden();
    }

    private function deployment(): Deployment
    {
        return Deployment::query()->create([
            'user_id' => $this->admin->id,
            'server_id' => fake()->unique()->numberBetween(1000, 999999),
            'domain' => fake()->unique()->domainName(),
            'version' => 'non-www',
            'status' => 'pending',
            'current_step' => 'pending',
        ]);
    }

    private function usage(
        ?Deployment $deployment,
        string $provider,
        bool $successful,
        string $operation = 'GET /resource',
    ): ApiUsageRecord {
        return ApiUsageRecord::query()->create([
            'deployment_id' => $deployment?->id,
            'provider' => $provider,
            'operation' => $operation,
            'method' => 'GET',
            'endpoint' => "https://api.example.test/{$provider}",
            'status_code' => $successful ? 200 : 429,
            'successful' => $successful,
            'duration_ms' => 25,
            'requested_at' => now(),
        ]);
    }
}
