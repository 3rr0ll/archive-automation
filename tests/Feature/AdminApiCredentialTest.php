<?php

namespace Tests\Feature;

use App\Models\ApiCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminApiCredentialTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        return [$admin, $token];
    }

    public function test_admin_can_list_api_credentials_without_token_data(): void
    {
        [, $token] = $this->actingAsAdmin();
        ApiCredential::factory()->create([
            'service' => 'runcloud',
            'token_data_encrypted' => 'secret-token',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/api-credentials');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissingPath('data.0.token_data');
    }

    public function test_admin_can_create_api_credential(): void
    {
        [, $token] = $this->actingAsAdmin();
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/api-credentials', [
                'user_id' => $user->id,
                'service' => 'cloudflare',
                'account_name' => 'ops@example.com',
                'token_data' => 'cf-api-token-123',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.service', 'cloudflare')
            ->assertJsonPath('data.token_data', 'cf-api-token-123');

        $this->assertDatabaseCount('api_credentials', 1);
        $this->assertSame('cf-api-token-123', ApiCredential::first()->tokenData());
    }

    public function test_admin_can_create_internet_archive_settings(): void
    {
        [$admin, $token] = $this->actingAsAdmin();
        $settings = json_encode([
            'allowed_hosts' => ['archive.org', 'archive-it.org'],
            'timeout' => 25,
            'poll_delay' => 90,
            'max_attempts' => 1000,
        ], JSON_THROW_ON_ERROR);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/api-credentials', [
                'user_id' => $admin->id,
                'service' => 'internet_archive',
                'account_name' => 'Global Internet Archive settings',
                'token_data' => $settings,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.service', 'internet_archive')
            ->assertJsonPath('data.token_data', $settings);

        $this->assertSame($settings, ApiCredential::first()->tokenData());
    }

    public function test_internet_archive_settings_are_validated(): void
    {
        [$admin, $token] = $this->actingAsAdmin();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/api-credentials', [
                'user_id' => $admin->id,
                'service' => 'internet_archive',
                'token_data' => json_encode([
                    'allowed_hosts' => ['http://archive.org/download'],
                    'timeout' => 0,
                    'poll_delay' => 60,
                    'max_attempts' => 1440,
                ], JSON_THROW_ON_ERROR),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('token_data');
    }

    public function test_admin_can_show_api_credential_with_token_data(): void
    {
        [, $token] = $this->actingAsAdmin();
        $credential = ApiCredential::factory()->create([
            'service' => 'gsc',
            'token_data_encrypted' => 'gsc-secret',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/api-credentials/{$credential->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $credential->id)
            ->assertJsonPath('data.token_data', 'gsc-secret');
    }

    public function test_admin_can_update_api_credential(): void
    {
        [, $token] = $this->actingAsAdmin();
        $credential = ApiCredential::factory()->create([
            'service' => 'runcloud',
            'account_name' => 'old-account',
            'token_data_encrypted' => 'old-token',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/api-credentials/{$credential->id}", [
                'account_name' => 'new-account',
                'token_data' => 'new-token',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account_name', 'new-account')
            ->assertJsonPath('data.token_data', 'new-token');

        $this->assertSame('new-token', $credential->fresh()->tokenData());
    }

    public function test_admin_can_delete_api_credential(): void
    {
        [, $token] = $this->actingAsAdmin();
        $credential = ApiCredential::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/api-credentials/{$credential->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'API credential deleted successfully.',
            ]);

        $this->assertDatabaseMissing('api_credentials', ['id' => $credential->id]);
    }

    public function test_admin_can_filter_api_credentials_by_service(): void
    {
        [, $token] = $this->actingAsAdmin();
        ApiCredential::factory()->create(['service' => 'runcloud']);
        ApiCredential::factory()->create(['service' => 'cloudflare']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/api-credentials?service=runcloud');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.service', 'runcloud');
    }

    public function test_non_admin_cannot_manage_api_credentials(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/api-credentials')
            ->assertForbidden();
    }
}
