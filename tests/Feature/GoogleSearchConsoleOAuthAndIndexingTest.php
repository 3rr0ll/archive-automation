<?php

namespace Tests\Feature;

use App\Models\GoogleSearchConsoleAccount;
use App\Models\User;
use App\Services\GoogleSearchConsole\GoogleSearchConsoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use Tests\TestCase;

class GoogleSearchConsoleOAuthAndIndexingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->regularUser = User::factory()->create(['role' => 'user']);

        config([
            'services.google_search_console.client_id' => 'test-client-id.apps.googleusercontent.com',
            'services.google_search_console.client_secret' => 'test-client-secret',
            'services.google_search_console.redirect_uri' => 'http://127.0.0.1:8000/google/callback',
            'services.google_search_console.timeout' => 30,
        ]);
    }

    public function test_admin_can_get_oauth_status(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/google-search-console/oauth/status')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('oauth_configured', true)
            ->assertJsonPath('client_id', 'test-client-id.apps.googleusercontent.com');
    }

    public function test_admin_can_generate_oauth_url(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/google-search-console/oauth/url')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['auth_url', 'redirect_uri', 'scopes']);

        $authUrl = (string) $response->json('auth_url');
        $this->assertStringContainsString('accounts.google.com', $authUrl);
        $this->assertStringContainsString('test-client-id', $authUrl);

        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $query);
        $state = json_decode(Crypt::decryptString($query['state']), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($this->admin->id, $state['user_id']);
        $this->assertGreaterThan(now()->getTimestamp(), $state['expires_at']);
    }

    public function test_google_oauth_client_honors_disabled_ssl_verification_in_testing(): void
    {
        config(['services.http.verify_ssl' => false]);

        $client = app(GoogleSearchConsoleService::class)->createBaseOAuthClient();

        $this->assertFalse($client->getHttpClient()->getConfig('verify'));
    }

    public function test_oauth_callback_rejects_a_missing_user_context_before_saving_an_account(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Google OAuth session expired.');

        app(GoogleSearchConsoleService::class)->handleOAuthCallback('unused-code');
    }

    public function test_google_service_account_client_honors_disabled_ssl_verification_in_testing(): void
    {
        config(['services.http.verify_ssl' => false]);

        $account = new GoogleSearchConsoleAccount;
        $account->credentials = [
            'type' => 'service_account',
            'client_id' => '123456789',
            'client_email' => 'test@example.iam.gserviceaccount.com',
            'private_key' => "-----BEGIN PRIVATE KEY-----\ntest\n-----END PRIVATE KEY-----\n",
        ];

        $client = app(GoogleSearchConsoleService::class)->buildClientFromAccount($account);

        $this->assertFalse($client->getHttpClient()->getConfig('verify'));
    }

    public function test_regular_user_cannot_access_gsc_endpoints(): void
    {
        $this->actingAs($this->regularUser, 'sanctum')
            ->getJson('/api/admin/google-search-console/oauth/status')
            ->assertForbidden();

        $this->actingAs($this->regularUser, 'sanctum')
            ->postJson('/api/admin/google-search-console/indexing/request', ['url' => 'https://example.com/page'])
            ->assertForbidden();

        $this->actingAs($this->regularUser, 'sanctum')
            ->postJson('/api/admin/google-search-console/domains/add', ['property' => 'sc-domain:example.com'])
            ->assertForbidden();
    }

    public function test_request_indexing_validates_input(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/google-search-console/indexing/request', [
                'url' => 'not-a-valid-url',
                'type' => 'INVALID_TYPE',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url', 'type']);
    }

    public function test_add_property_validates_input(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/google-search-console/domains/add', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['property']);
    }

    public function test_oauth_exchange_validates_code_or_refresh_token(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/google-search-console/oauth/exchange', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'refresh_token']);
    }

    public function test_web_callback_handles_missing_code(): void
    {
        $response = $this->get('/google/callback');
        $response->assertRedirect(route('google-search-console'));
        $response->assertSessionHas('error');
    }

    public function test_web_callback_returns_json_when_requested(): void
    {
        $response = $this->getJson('/google/callback');
        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }
}
