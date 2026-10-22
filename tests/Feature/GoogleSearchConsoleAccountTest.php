<?php

namespace Tests\Feature;

use App\Models\GoogleSearchConsoleAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GoogleSearchConsoleAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_add_an_encrypted_service_account_without_exposing_credentials(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/google-search-console/accounts', [
                'name' => 'Production',
                'owner_email' => 'owner@example.com',
                'credentials_json' => json_encode($this->credentials('one@example.iam.gserviceaccount.com')),
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.client_email', 'one@example.iam.gserviceaccount.com')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonMissingPath('data.credentials');

        $response->assertJsonMissing(['private_key' => 'private-key-value']);

        $stored = (string) DB::table('google_search_console_accounts')->value('credentials');
        $this->assertStringNotContainsString('private-key-value', $stored);
        $this->assertSame(
            'private-key-value',
            GoogleSearchConsoleAccount::firstOrFail()->credentials['private_key'],
        );
        $this->assertSame($this->admin->id, GoogleSearchConsoleAccount::firstOrFail()->user_id);
    }

    public function test_activating_an_account_deactivates_the_previous_account(): void
    {
        $first = $this->createAccount('First', 'first@example.iam.gserviceaccount.com', true);
        $second = $this->createAccount('Second', 'second@example.iam.gserviceaccount.com', false);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/google-search-console/accounts/{$second->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertSame(1, GoogleSearchConsoleAccount::query()->where('is_active', true)->count());
    }

    public function test_invalid_service_account_json_is_rejected(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/google-search-console/accounts', [
                'name' => 'Invalid',
                'credentials_json' => json_encode(['type' => 'authorized_user']),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('credentials_json');
    }

    public function test_regular_users_cannot_manage_google_accounts_or_domains(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/google-search-console/accounts')
            ->assertForbidden();
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/google-search-console/accounts', [])
            ->assertForbidden();
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/google-search-console/domains/register', ['domain' => 'example.com'])
            ->assertForbidden();
    }

    private function createAccount(string $name, string $email, bool $active): GoogleSearchConsoleAccount
    {
        return GoogleSearchConsoleAccount::query()->create([
            'name' => $name,
            'client_email' => $email,
            'credentials' => $this->credentials($email),
            'is_active' => $active,
            'user_id' => $this->admin->id,
        ]);
    }

    /** @return array<string, string> */
    private function credentials(string $email): array
    {
        return [
            'type' => 'service_account',
            'project_id' => 'example-project',
            'private_key_id' => 'key-id',
            'private_key' => 'private-key-value',
            'client_email' => $email,
            'client_id' => '123456789',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ];
    }
}
