<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GoogleSearchConsole\GoogleSearchConsoleSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GoogleSearchConsoleSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        config()->set('services.google_search_console.client_id', 'oauth-client-id');
        config()->set('services.google_search_console.client_secret', 'oauth-client-secret');
        config()->set('services.google_search_console.redirect_uri', 'https://app.example.com/google-search-console/callback');
    }

    public function test_connection_uses_existing_encrypted_environment_variable_storage(): void
    {
        $settings = app(GoogleSearchConsoleSettings::class);
        $settings->saveConnection('refresh-token-value', 'Owner@Example.com', $this->admin->id);

        $this->assertDatabaseCount('environment_variables', 3);
        $this->assertSame('refresh-token-value', $settings->refreshToken());
        $this->assertSame('owner@example.com', $settings->accountEmail());
        $this->assertTrue($settings->active());

        $storedValue = (string) DB::table('environment_variables')
            ->where('key', 'GOOGLE_OAUTH_REFRESH_TOKEN')
            ->value('value');

        $this->assertNotSame('refresh-token-value', $storedValue);
        $this->assertStringNotContainsString('refresh-token-value', $storedValue);
    }

    public function test_admin_can_read_and_toggle_the_single_connection(): void
    {
        $settings = app(GoogleSearchConsoleSettings::class);
        $settings->saveConnection('refresh-token-value', 'owner@example.com', $this->admin->id);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/google-search-console/settings')
            ->assertOk()
            ->assertJsonPath('data.client_configured', true)
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.account_email', 'owner@example.com');

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/admin/google-search-console/settings', ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.active', false);
    }

    public function test_connection_can_be_removed_without_a_google_accounts_table(): void
    {
        $settings = app(GoogleSearchConsoleSettings::class);
        $settings->saveConnection('refresh-token-value', 'owner@example.com', $this->admin->id);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/admin/google-search-console/connection')
            ->assertOk()
            ->assertJsonPath('data.connected', false)
            ->assertJsonPath('data.active', false);

        $this->assertDatabaseCount('environment_variables', 0);
    }

    public function test_connection_cannot_be_activated_before_oauth_is_completed(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/admin/google-search-console/settings', ['active' => true])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('environment_variables', 0);
    }

    public function test_regular_users_cannot_manage_google_search_console(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/google-search-console/settings')
            ->assertForbidden();
        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/admin/google-search-console/settings', ['active' => true])
            ->assertForbidden();
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/google-search-console/domains/register', ['domain' => 'example.com'])
            ->assertForbidden();
    }
}
