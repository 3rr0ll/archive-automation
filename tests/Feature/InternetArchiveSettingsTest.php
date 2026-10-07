<?php

namespace Tests\Feature;

use App\Models\ApiCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InternetArchiveSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_read_environment_backed_settings(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/settings/internet-archive')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('source', 'environment')
            ->assertJsonStructure([
                'data' => ['allowed_hosts', 'timeout', 'poll_delay', 'max_attempts'],
            ]);
    }

    public function test_admin_can_update_structured_settings_without_creating_duplicates(): void
    {
        ApiCredential::factory()->for($this->admin)->internetArchive()->count(2)->create();
        $payload = [
            'allowed_hosts' => ['ARCHIVE.ORG', 'archive-it.org'],
            'timeout' => 25,
            'poll_delay' => 90,
            'max_attempts' => 1000,
        ];

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/settings/internet-archive', $payload)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('source', 'database')
            ->assertJsonPath('data.allowed_hosts.0', 'archive.org')
            ->assertJsonPath('data.timeout', 25)
            ->assertJsonPath('data.poll_delay', 90)
            ->assertJsonPath('data.max_attempts', 1000);

        $this->assertDatabaseCount('api_credentials', 1);
        $settings = json_decode((string) ApiCredential::first()->tokenData(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('internet_archive', ApiCredential::first()->service);
        $this->assertSame(25, $settings['timeout']);
    }

    public function test_settings_validation_rejects_unsafe_or_out_of_range_values(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/settings/internet-archive', [
                'allowed_hosts' => ['http://archive.org/download'],
                'timeout' => 0,
                'poll_delay' => 0,
                'max_attempts' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'allowed_hosts.0',
                'timeout',
                'poll_delay',
                'max_attempts',
            ]);
    }

    public function test_admin_can_test_the_configured_archive_connection(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://archive.org/' => Http::response('', 200),
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/settings/internet-archive/test')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.url', 'https://archive.org/');

        Http::assertSent(fn ($request): bool => $request->method() === 'HEAD'
            && $request->url() === 'https://archive.org/');
    }

    public function test_connection_test_rejects_a_host_outside_the_allowlist(): void
    {
        Http::preventStrayRequests();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/settings/internet-archive/test', [
                'url' => 'https://example.com/',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('url');

        Http::assertNothingSent();
    }

    public function test_regular_users_cannot_manage_internet_archive_settings(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/settings/internet-archive')
            ->assertForbidden();
        $this->actingAs($user, 'sanctum')
            ->putJson('/api/admin/settings/internet-archive', [])
            ->assertForbidden();
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/settings/internet-archive/test')
            ->assertForbidden();
    }

    public function test_generic_credential_endpoints_do_not_expose_archive_settings(): void
    {
        $credential = ApiCredential::factory()->for($this->admin)->internetArchive()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/api-credentials')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/api-credentials/{$credential->id}")
            ->assertNotFound();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/api-credentials', [
                'user_id' => $this->admin->id,
                'service' => 'internet_archive',
                'token_data' => 'not-allowed-here',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service');
    }
}
