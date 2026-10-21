<?php

namespace App\Services\GoogleSearchConsole;

use App\Models\GoogleSearchConsoleAccount;
use App\Services\Logging\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoogleSearchConsoleAccountService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @return Collection<int, GoogleSearchConsoleAccount> */
    public function all(): Collection
    {
        return GoogleSearchConsoleAccount::query()
            ->latest()
            ->get();
    }

    /**
     * @param  array{name: string, owner_email?: string|null, credentials: array<string, mixed>, is_active?: bool}  $data
     */
    public function create(array $data, ?int $userId): GoogleSearchConsoleAccount
    {
        $credentials = $this->validateCredentials($data['credentials']);
        $this->ensureClientEmailIsUnique((string) $credentials['client_email']);

        return DB::transaction(function () use ($data, $credentials, $userId): GoogleSearchConsoleAccount {
            if ($data['is_active'] ?? false) {
                GoogleSearchConsoleAccount::query()->update(['is_active' => false]);
            }

            $account = GoogleSearchConsoleAccount::query()->create([
                'name' => $data['name'],
                'client_email' => $credentials['client_email'],
                'credentials' => $credentials,
                'owner_email' => $data['owner_email'] ?? null,
                'is_active' => $data['is_active'] ?? false,
                'user_id' => $userId,
            ]);

            $this->auditLogger->record('google-search-console.account-created', $account, [
                'client_email' => $account->client_email,
                'is_active' => $account->is_active,
            ]);

            return $account;
        });
    }

    /**
     * @param  array{name?: string, owner_email?: string|null, credentials?: array<string, mixed>, is_active?: bool}  $data
     */
    public function update(GoogleSearchConsoleAccount $account, array $data, ?int $userId): GoogleSearchConsoleAccount
    {
        return DB::transaction(function () use ($account, $data, $userId): GoogleSearchConsoleAccount {
            if (($data['is_active'] ?? false) === true) {
                GoogleSearchConsoleAccount::query()
                    ->whereKeyNot($account->getKey())
                    ->update(['is_active' => false]);
            }

            if (array_key_exists('credentials', $data)) {
                $credentials = $this->validateCredentials($data['credentials']);
                $this->ensureClientEmailIsUnique((string) $credentials['client_email'], $account);
                $data['credentials'] = $credentials;
                $data['client_email'] = $credentials['client_email'];
            }

            $account->fill($data);
            $account->user_id = $userId;
            $account->save();

            $this->auditLogger->record('google-search-console.account-updated', $account, [
                'client_email' => $account->client_email,
                'is_active' => $account->is_active,
            ]);

            return $account->refresh();
        });
    }

    public function activate(GoogleSearchConsoleAccount $account, ?int $userId): GoogleSearchConsoleAccount
    {
        return DB::transaction(function () use ($account, $userId): GoogleSearchConsoleAccount {
            GoogleSearchConsoleAccount::query()->update(['is_active' => false]);
            $account->update(['is_active' => true, 'user_id' => $userId]);

            $this->auditLogger->record('google-search-console.account-activated', $account, [
                'client_email' => $account->client_email,
            ]);

            return $account->refresh();
        });
    }

    public function delete(GoogleSearchConsoleAccount $account): void
    {
        $context = ['client_email' => $account->client_email, 'was_active' => $account->is_active];
        $this->auditLogger->record('google-search-console.account-deleted', $account, $context);
        $account->delete();
    }

    public function active(): ?GoogleSearchConsoleAccount
    {
        return GoogleSearchConsoleAccount::query()->where('is_active', true)->first();
    }

    /**
     * Create or update an OAuth account and set it active.
     *
     * @param  array<string, mixed>  $token
     */
    public function createOrUpdateOAuthAccount(
        string $email,
        array $token,
        ?int $userId = null,
        ?string $name = null,
    ): GoogleSearchConsoleAccount {
        return DB::transaction(function () use ($email, $token, $userId, $name): GoogleSearchConsoleAccount {
            GoogleSearchConsoleAccount::query()->update(['is_active' => false]);

            $account = GoogleSearchConsoleAccount::query()->firstOrNew(['client_email' => $email]);
            $isNew = ! $account->exists;

            $account->fill([
                'name' => $name ?? "Google OAuth ({$email})",
                'client_email' => $email,
                'credentials' => [
                    'type' => 'oauth',
                    'token' => $token,
                    'client_id' => config('services.google_search_console.client_id'),
                ],
                'owner_email' => $email,
                'is_active' => true,
                'user_id' => $userId,
            ]);
            $account->save();

            $event = $isNew ? 'google-search-console.account-created' : 'google-search-console.account-updated';
            $this->auditLogger->record($event, $account, [
                'client_email' => $account->client_email,
                'is_active' => true,
                'type' => 'oauth',
            ]);

            return $account->refresh();
        });
    }

    /**
     * Persist refreshed tokens without clobbering existing refresh_token.
     *
     * @param  array<string, mixed>  $newToken
     */
    public function updateToken(GoogleSearchConsoleAccount $account, array $newToken): void
    {
        $credentials = $account->credentials;
        $currentToken = $credentials['token'] ?? [];

        if (empty($newToken['refresh_token']) && ! empty($currentToken['refresh_token'])) {
            $newToken['refresh_token'] = $currentToken['refresh_token'];
        }

        $credentials['token'] = array_merge($currentToken, $newToken);
        $account->update(['credentials' => $credentials]);
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     */
    private function validateCredentials(array $credentials): array
    {
        $type = $credentials['type'] ?? null;

        if ($type === 'oauth') {
            $token = $credentials['token'] ?? [];
            if (empty($token['access_token']) && empty($token['refresh_token'])) {
                throw ValidationException::withMessages([
                    'credentials_json' => ['Enter a valid OAuth token payload with an access or refresh token.'],
                ]);
            }

            return $credentials;
        }

        if ($type !== 'service_account'
            || blank($credentials['client_email'] ?? null)
            || blank($credentials['private_key'] ?? null)) {
            throw ValidationException::withMessages([
                'credentials_json' => ['Enter a valid Google service-account JSON key.'],
            ]);
        }

        return $credentials;
    }

    private function ensureClientEmailIsUnique(
        string $clientEmail,
        ?GoogleSearchConsoleAccount $except = null,
    ): void {
        $exists = GoogleSearchConsoleAccount::query()
            ->where('client_email', $clientEmail)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'credentials_json' => ['This Google account has already been added.'],
            ]);
        }
    }
}
