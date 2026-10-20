<?php

namespace App\Services\GoogleSearchConsole;

use App\Models\EnvironmentVariable;
use App\Services\Logging\AuditLogger;

class GoogleSearchConsoleSettings
{
    private const REFRESH_TOKEN_KEY = 'GOOGLE_OAUTH_REFRESH_TOKEN';

    private const ACCOUNT_EMAIL_KEY = 'GOOGLE_OAUTH_ACCOUNT_EMAIL';

    private const ACTIVE_KEY = 'GOOGLE_SEARCH_CONSOLE_ACTIVE';

    public function __construct(
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @return array{client_configured: bool, connected: bool, active: bool, account_email: ?string, redirect_uri: ?string}
     */
    public function status(): array
    {
        return [
            'client_configured' => $this->clientConfigured(),
            'connected' => $this->connected(),
            'active' => $this->active(),
            'account_email' => $this->accountEmail(),
            'redirect_uri' => $this->redirectUri() ?: null,
        ];
    }

    public function clientConfigured(): bool
    {
        return $this->clientId() !== ''
            && $this->clientSecret() !== ''
            && $this->redirectUri() !== '';
    }

    public function connected(): bool
    {
        return $this->refreshToken() !== null;
    }

    public function active(): bool
    {
        $value = $this->databaseValue(self::ACTIVE_KEY);

        return filter_var($value ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public function clientId(): string
    {
        return trim((string) config('services.google_search_console.client_id'));
    }

    public function clientSecret(): string
    {
        return trim((string) config('services.google_search_console.client_secret'));
    }

    public function redirectUri(): string
    {
        return trim((string) config('services.google_search_console.redirect_uri'));
    }

    public function refreshToken(): ?string
    {
        $value = $this->databaseValue(self::REFRESH_TOKEN_KEY);

        return filled($value) ? $value : null;
    }

    public function accountEmail(): ?string
    {
        $value = $this->databaseValue(self::ACCOUNT_EMAIL_KEY);

        return filled($value) ? $value : null;
    }

    public function saveConnection(string $refreshToken, string $email, ?int $userId): void
    {
        $this->store(self::REFRESH_TOKEN_KEY, $refreshToken, 'secret', $userId);
        $this->store(self::ACCOUNT_EMAIL_KEY, strtolower($email), 'secret', $userId);
        $this->store(self::ACTIVE_KEY, 'true', 'config', $userId);

        $this->auditLogger->record('google-search-console.connected', context: [
            'account_email' => strtolower($email),
        ]);
    }

    public function setActive(bool $active, ?int $userId): void
    {
        $this->store(self::ACTIVE_KEY, $active ? 'true' : 'false', 'config', $userId);
        $this->auditLogger->record('google-search-console.activation-changed', context: [
            'active' => $active,
        ]);
    }

    public function disconnect(): void
    {
        EnvironmentVariable::query()
            ->whereIn('key', [self::REFRESH_TOKEN_KEY, self::ACCOUNT_EMAIL_KEY, self::ACTIVE_KEY])
            ->delete();

        $this->auditLogger->record('google-search-console.disconnected');
    }

    private function databaseValue(string $key): ?string
    {
        $variable = EnvironmentVariable::query()
            ->where('key', $key)
            ->first();

        // Read through the model so Laravel applies the encrypted cast.
        return $variable?->value;
    }

    private function store(string $key, string $value, string $type, ?int $userId): void
    {
        EnvironmentVariable::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'environments' => 'Production',
                'note' => 'Managed by Google Search Console OAuth settings.',
                'user_id' => $userId,
            ],
        );
    }
}
