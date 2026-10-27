<?php

namespace App\Services\GoogleSearchConsole;

use App\Models\GoogleSearchConsoleAccount;
use App\Models\User;
use App\Services\ApiUsage\ApiUsageRecorder;
use App\Services\Cloudflare\CloudflareDnsService;
use App\Services\Http\ExternalHttpClient;
use Google\Client as GoogleClient;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\SearchConsole;
use Google\Service\SiteVerification;
use Google\Service\SiteVerification\SiteVerificationWebResourceGettokenRequest;
use Google\Service\SiteVerification\SiteVerificationWebResourceGettokenRequestSite;
use Google\Service\SiteVerification\SiteVerificationWebResourceResource;
use Google\Service\SiteVerification\SiteVerificationWebResourceResourceSite;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GoogleSearchConsoleService
{
    private const VERIFY_ATTEMPTS = 5;

    private const VERIFY_DELAY_SECONDS = 30;

    private ?GoogleClient $client = null;

    public function __construct(
        private CloudflareDnsService $dnsService,
        private ApiUsageRecorder $usageRecorder,
        private GoogleSearchConsoleAccountService $accounts,
        private ExternalHttpClient $externalHttpClient,
    ) {}

    /**
     * Register the domain as a Search Console domain property.
     *
     * Requests a DNS TXT token, publishes it through Cloudflare, verifies
     * ownership, then adds the "sc-domain:" property to Search Console.
     */
    public function verifyProperty(string $domain): bool
    {
        $this->ensureEnabled();
        $domain = $this->normalizeDomain($domain);
        $verification = new SiteVerification($this->client());

        $tokenSite = new SiteVerificationWebResourceGettokenRequestSite;
        $tokenSite->setType('INET_DOMAIN');
        $tokenSite->setIdentifier($domain);

        $tokenRequest = new SiteVerificationWebResourceGettokenRequest;
        $tokenRequest->setSite($tokenSite);
        $tokenRequest->setVerificationMethod('DNS_TXT');

        $token = (string) $this->call('request verification token', $domain,
            fn () => $verification->webResource->getToken($tokenRequest)->getToken());

        if ($token === '') {
            throw new RuntimeException('Google did not return a verification token.');
        }

        if (! $this->hasTxtRecord($domain, $token)) {
            $this->dnsService->addDnsRecord($domain, 'TXT', $domain, $token, false, 300);
        }

        $resourceSite = new SiteVerificationWebResourceResourceSite;
        $resourceSite->setType('INET_DOMAIN');
        $resourceSite->setIdentifier($domain);

        $resource = new SiteVerificationWebResourceResource;
        $resource->setSite($resourceSite);

        // DNS propagation can lag behind the Cloudflare write, so retry verification.
        for ($attempt = 1; ; $attempt++) {
            $startedAt = microtime(true);
            try {
                $verifiedResource = $verification->webResource->insert('DNS_TXT', $resource);
                $this->recordUsage('verify domain ownership', 200, true, $startedAt);
                break;
            } catch (GoogleServiceException $exception) {
                $this->recordUsage('verify domain ownership', (int) $exception->getCode(), false, $startedAt);
                if ($attempt >= self::VERIFY_ATTEMPTS) {
                    $this->fail('verify domain ownership', $domain, $exception);
                }
                sleep(self::VERIFY_DELAY_SECONDS);
            } catch (Throwable $exception) {
                $this->recordUsage('verify domain ownership', 0, false, $startedAt);

                throw $exception;
            }
        }

        $this->addHumanOwner($verification, $verifiedResource);

        $searchConsole = new SearchConsole($this->client());
        $this->call('add Search Console property', $domain,
            fn () => $searchConsole->sites->add($this->propertyUrl($domain)));

        Log::info('Google Search Console property registered.', ['domain' => $domain]);

        return true;
    }

    /**
     * Add a site property directly to Google Search Console without running DNS TXT verification.
     *
     * @return array{property: string, added: bool}
     */
    public function addProperty(string $domainOrUrl): array
    {
        $this->ensureEnabled();
        $property = $this->propertyUrl($domainOrUrl);

        $searchConsole = new SearchConsole($this->client());
        $this->call('add Search Console property', $property,
            fn () => $searchConsole->sites->add($property));

        Log::info('Google Search Console property added.', ['property' => $property]);

        return [
            'property' => $property,
            'added' => true,
        ];
    }

    /**
     * Submit a sitemap for the domain property.
     */
    public function submitSitemap(string $domain, string $sitemapUrl = ''): bool
    {
        $this->ensureEnabled();
        $property = $this->propertyUrl($domain);
        $normalizedDomain = $this->normalizeDomain($domain);
        $sitemapUrl = $sitemapUrl !== '' ? $sitemapUrl : "https://{$normalizedDomain}/sitemap.xml";

        $searchConsole = new SearchConsole($this->client());
        $this->call('submit sitemap', $normalizedDomain,
            fn () => $searchConsole->sitemaps->submit($property, $sitemapUrl));

        Log::info('Sitemap submitted to Google Search Console.', [
            'domain' => $normalizedDomain,
            'property' => $property,
            'sitemap' => $sitemapUrl,
        ]);

        return true;
    }

    /**
     * Request indexing for a specific URL using Google's Indexing API.
     *
     * @return array<string, mixed>
     */
    public function requestIndexing(string $url, string $type = 'URL_UPDATED'): array
    {
        $this->ensureEnabled();

        $validTypes = ['URL_UPDATED', 'URL_DELETED'];
        if (! in_array($type, $validTypes, true)) {
            throw new RuntimeException("Invalid indexing type '{$type}'. Allowed types: " . implode(', ', $validTypes));
        }

        $url = trim($url);
        $startedAt = microtime(true);
        $client = $this->client();
        $httpClient = $client->authorize();

        try {
            $response = $httpClient->post('https://indexing.googleapis.com/v3/urlNotifications:publish', [
                'json' => [
                    'url' => $url,
                    'type' => $type,
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'timeout' => (int) config('services.google_search_console.timeout', 30),
                'verify' => $this->shouldVerifySsl(),
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode((string) $response->getBody(), true) ?? [];
            $this->recordUsage('request url indexing', $statusCode, true, $startedAt);

            Log::info('Google indexing notification published.', [
                'url' => $url,
                'type' => $type,
                'response' => $body,
            ]);

            return [
                'url' => $url,
                'type' => $type,
                'success' => true,
                'data' => $body,
            ];
        } catch (RequestException $exception) {
            $response = $exception->getResponse();
            $statusCode = $response ? $response->getStatusCode() : 500;
            $rawBody = $response ? (string) $response->getBody() : $exception->getMessage();
            $this->recordUsage('request url indexing', $statusCode, false, $startedAt);

            $errorData = json_decode($rawBody, true);
            $message = $errorData['error']['message'] ?? $exception->getMessage();

            Log::error('Google Indexing API request failed.', [
                'url' => $url,
                'type' => $type,
                'status' => $statusCode,
                'error' => $message,
            ]);

            throw new RuntimeException("Google Indexing API failed for {$url}: {$message}", $statusCode, $exception);
        } catch (Throwable $exception) {
            $this->recordUsage('request url indexing', 0, false, $startedAt);

            throw $exception;
        }
    }

    /**
     * Get indexing metadata for a URL using Google's Indexing API.
     *
     * @return array<string, mixed>
     */
    public function getIndexingStatus(string $url): array
    {
        $this->ensureEnabled();

        $url = trim($url);
        $startedAt = microtime(true);
        $client = $this->client();
        $httpClient = $client->authorize();

        try {
            $response = $httpClient->get('https://indexing.googleapis.com/v3/urlNotifications/metadata', [
                'query' => ['url' => $url],
                'timeout' => (int) config('services.google_search_console.timeout', 30),
                'verify' => $this->shouldVerifySsl(),
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode((string) $response->getBody(), true) ?? [];
            $this->recordUsage('get indexing metadata', $statusCode, true, $startedAt);

            return [
                'url' => $url,
                'success' => true,
                'data' => $body,
            ];
        } catch (RequestException $exception) {
            $response = $exception->getResponse();
            $statusCode = $response ? $response->getStatusCode() : 500;
            $rawBody = $response ? (string) $response->getBody() : $exception->getMessage();
            $this->recordUsage('get indexing metadata', $statusCode, false, $startedAt);

            $errorData = json_decode($rawBody, true);
            $message = $errorData['error']['message'] ?? $exception->getMessage();

            throw new RuntimeException("Google Indexing API metadata request failed: {$message}", $statusCode, $exception);
        } catch (Throwable $exception) {
            $this->recordUsage('get indexing metadata', 0, false, $startedAt);

            throw $exception;
        }
    }

    /**
     * Full workflow: add/verify property, submit sitemap, and optionally request indexing.
     *
     * @return array{domain: string, verified: bool, sitemap: bool, sitemap_url: string, indexing_requested: bool, indexing_details: ?array<string, mixed>}
     */
    public function registerDomain(
        string $domain,
        string $sitemapUrl = '',
        bool $verifyOwnership = true,
        bool $requestIndexing = true,
    ): array {
        $normalizedDomain = $this->normalizeDomain($domain);
        $sitemapUrl = $sitemapUrl !== '' ? $sitemapUrl : "https://{$normalizedDomain}/sitemap.xml";

        $verified = false;
        if ($verifyOwnership) {
            $verified = $this->verifyProperty($normalizedDomain);
        } else {
            $this->addProperty($normalizedDomain);
        }

        $sitemap = $this->submitSitemap($normalizedDomain, $sitemapUrl);

        $indexingResult = null;
        if ($requestIndexing) {
            try {
                $indexingResult = $this->requestIndexing("https://{$normalizedDomain}/");
            } catch (Throwable $e) {
                Log::warning('Indexing request during domain registration skipped/failed.', [
                    'domain' => $normalizedDomain,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'domain' => $normalizedDomain,
            'verified' => $verified,
            'sitemap' => $sitemap,
            'sitemap_url' => $sitemapUrl,
            'indexing_requested' => $indexingResult !== null && ($indexingResult['success'] ?? false),
            'indexing_details' => $indexingResult,
        ];
    }

    public function isEnabled(): bool
    {
        return $this->accounts->active() !== null
            || $this->hasOAuthEnvFallback()
            || $this->hasLegacyFallback();
    }

    /**
     * Retrieve configuration from DB EnvironmentVariable first, then config/.env fallback.
     */
    public function getEnvironmentValue(string $key, mixed $fallback = null): mixed
    {
        try {
            $variable = \App\Models\EnvironmentVariable::query()
                ->where('key', $key)
                ->latest('updated_at')
                ->latest('id')
                ->first();

            if ($variable !== null && filled($variable->value)) {
                return $variable->value;
            }
        } catch (\Throwable) {
            // DB may not be reachable or table not migrated yet
        }

        return $fallback;
    }

    public function getClientId(): ?string
    {
        return $this->getEnvironmentValue('GOOGLE_CLIENT_ID', config('services.google_search_console.client_id'));
    }

    public function getClientSecret(): ?string
    {
        return $this->getEnvironmentValue('GOOGLE_CLIENT_SECRET', config('services.google_search_console.client_secret'));
    }

    public function getRedirectUri(): string
    {
        return (string) $this->getEnvironmentValue('GOOGLE_REDIRECT_URI', config('services.google_search_console.redirect_uri', 'http://ara.scriptxyz.xyz/google/callback'));
    }

    public function hasOAuthConfiguration(): bool
    {
        return filled($this->getClientId()) && filled($this->getClientSecret());
    }

    public function hasOAuthEnvFallback(): bool
    {
        $refreshToken = $this->getEnvironmentValue('GOOGLE_REFRESH_TOKEN', config('services.google_search_console.refresh_token'));
        return $this->hasOAuthConfiguration() && filled($refreshToken);
    }

    public function createOAuthUrl(?int $userId = null): string
    {
        if (! $this->hasOAuthConfiguration()) {
            throw new RuntimeException('GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET must be configured in Environment Variables or .env to use Google OAuth.');
        }

        $client = $this->createBaseOAuthClient();
        if ($userId !== null) {
            $client->setState(Crypt::encryptString(json_encode([
                'user_id' => $userId,
                'expires_at' => now()->addMinutes(10)->getTimestamp(),
            ], JSON_THROW_ON_ERROR)));
        }

        return $client->createAuthUrl();
    }

    public function handleOAuthCallback(
        string $code,
        ?int $userId = null,
        ?string $state = null,
    ): GoogleSearchConsoleAccount
    {
        $userId ??= $this->userIdFromOAuthState($state);
        if ($userId === null) {
            throw new RuntimeException('The Google OAuth session expired. Start the connection again while signed in.');
        }

        $client = $this->createBaseOAuthClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new RuntimeException('Google authorization failed: ' . ($token['error_description'] ?? $token['error']));
        }

        $client->setAccessToken($token);

        $email = null;
        if (! empty($token['id_token'])) {
            $payload = $client->verifyIdToken($token['id_token']);
            if (is_array($payload) && ! empty($payload['email'])) {
                $email = (string) $payload['email'];
            }
        }

        if (empty($email)) {
            try {
                $response = $client->authorize()->get('https://www.googleapis.com/oauth2/v3/userinfo');
                $userData = json_decode((string) $response->getBody(), true);
                $email = $userData['email'] ?? null;
            } catch (Throwable) {
                $email = config('services.google_search_console.owner_email') ?: 'oauth-connected-user@gmail.com';
            }
        }

        return $this->accounts->createOrUpdateOAuthAccount(
            email: (string) $email,
            token: $token,
            userId: $userId,
            name: "Google OAuth ({$email})",
        );
    }

    private function userIdFromOAuthState(?string $state): ?int
    {
        if (blank($state)) {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($state), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        $userId = filter_var($payload['user_id'] ?? null, FILTER_VALIDATE_INT);
        $expiresAt = filter_var($payload['expires_at'] ?? null, FILTER_VALIDATE_INT);

        if ($userId === false || $expiresAt === false || $expiresAt < now()->getTimestamp()) {
            return null;
        }

        return User::query()->whereKey($userId)->exists() ? $userId : null;
    }

    public function handleRefreshToken(string $refreshToken, ?string $email = null, ?int $userId = null): GoogleSearchConsoleAccount
    {
        $client = $this->createBaseOAuthClient();
        $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);

        if (isset($token['error'])) {
            throw new RuntimeException('Failed to exchange refresh token: ' . ($token['error_description'] ?? $token['error']));
        }

        $token['refresh_token'] = $refreshToken;
        $client->setAccessToken($token);

        if (empty($email)) {
            try {
                $response = $client->authorize()->get('https://www.googleapis.com/oauth2/v3/userinfo');
                $userData = json_decode((string) $response->getBody(), true);
                $email = $userData['email'] ?? null;
            } catch (Throwable) {
                $email = config('services.google_search_console.owner_email') ?: 'oauth-connected-user@gmail.com';
            }
        }

        return $this->accounts->createOrUpdateOAuthAccount(
            email: (string) $email,
            token: $token,
            userId: $userId,
            name: "Google OAuth ({$email})",
        );
    }

    public function hasLegacyFallback(): bool
    {
        $path = (string) config('services.google_search_console.credentials_path');

        return (bool) config('services.google_search_console.enabled')
            && $path !== ''
            && is_file($path);
    }

    /**
     * Validate credentials and confirm that the Search Console API is reachable.
     *
     * @return array{accessible_properties: int, account_email: ?string}
     */
    public function testAccount(GoogleSearchConsoleAccount $account): array
    {
        $startedAt = microtime(true);

        try {
            $response = (new SearchConsole($this->buildClientFromAccount($account)))
                ->sites
                ->listSites();
            $this->recordUsage('test account connection', 200, true, $startedAt);

            return [
                'accessible_properties' => count($response->getSiteEntry() ?? []),
                'account_email' => $account->client_email,
            ];
        } catch (GoogleServiceException $exception) {
            $this->recordUsage('test account connection', (int) $exception->getCode(), false, $startedAt);
            $this->fail('test account connection', $account->client_email, $exception);
        } catch (Throwable $exception) {
            $this->recordUsage('test account connection', 0, false, $startedAt);

            throw $exception;
        }
    }

    private function client(): GoogleClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $account = $this->accounts->active();
        if ($account !== null) {
            return $this->client = $this->buildClientFromAccount($account);
        }

        if ($this->hasOAuthEnvFallback()) {
            return $this->client = $this->buildOAuthClientFromEnv();
        }

        $path = (string) config('services.google_search_console.credentials_path');
        if ($this->hasLegacyFallback() && is_file($path)) {
            return $this->client = $this->buildClient($path);
        }

        throw new RuntimeException('No active Google Search Console account configured. Authorize with Google OAuth or activate a service account.');
    }

    private function ensureEnabled(): void
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Add and activate a Google Search Console account or authorize via OAuth first.');
        }
    }

    public function buildClientFromAccount(GoogleSearchConsoleAccount $account): GoogleClient
    {
        $credentials = $account->credentials;
        if (($credentials['type'] ?? '') === 'oauth') {
            return $this->buildOAuthClient($account);
        }

        return $this->buildClient($credentials);
    }

    private function buildOAuthClient(GoogleSearchConsoleAccount $account): GoogleClient
    {
        $client = $this->createBaseOAuthClient();
        $token = $account->credentials['token'] ?? [];
        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            $refreshToken = $client->getRefreshToken() ?? ($token['refresh_token'] ?? null);
            if (! empty($refreshToken)) {
                $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);
                $this->accounts->updateToken($account, $newToken);
            }
        }

        return $client;
    }

    private function buildOAuthClientFromEnv(): GoogleClient
    {
        $client = $this->createBaseOAuthClient();
        $refreshToken = (string) $this->getEnvironmentValue('GOOGLE_REFRESH_TOKEN', config('services.google_search_console.refresh_token'));
        $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);
        $token['refresh_token'] = $refreshToken;
        $client->setAccessToken($token);

        return $client;
    }

    public function createBaseOAuthClient(): GoogleClient
    {
        $client = new GoogleClient;
        $client->setApplicationName((string) config('app.name'));
        $client->setClientId((string) $this->getClientId());
        $client->setClientSecret((string) $this->getClientSecret());
        $client->setRedirectUri((string) $this->getRedirectUri());
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setScopes([
            SiteVerification::SITEVERIFICATION,
            SearchConsole::WEBMASTERS,
            'https://www.googleapis.com/auth/indexing',
            'openid',
            'email',
        ]);
        $client->setHttpClient(new Client([
            'timeout' => (int) config('services.google_search_console.timeout', 30),
            'verify' => $this->shouldVerifySsl(),
        ]));

        return $client;
    }

    /** @param array<string, mixed>|string $credentials */
    private function buildClient(array|string $credentials): GoogleClient
    {
        $client = new GoogleClient;
        $client->setApplicationName((string) config('app.name'));
        $client->setAuthConfig($credentials);
        $client->setScopes([
            SiteVerification::SITEVERIFICATION,
            SearchConsole::WEBMASTERS,
            'https://www.googleapis.com/auth/indexing',
        ]);
        $client->setHttpClient(new Client([
            'timeout' => (int) config('services.google_search_console.timeout', 30),
            'verify' => $this->shouldVerifySsl(),
        ]));

        return $client;
    }

    private function shouldVerifySsl(): bool
    {
        return $this->externalHttpClient->shouldVerifySsl();
    }

    private function addHumanOwner(
        SiteVerification $verification,
        SiteVerificationWebResourceResource $resource,
    ): void {
        $ownerEmail = $this->ownerEmail();
        if ($ownerEmail === null || blank($resource->getId())) {
            return;
        }

        $active = $this->accounts->active();
        if ($active && ($active->credentials['type'] ?? '') === 'oauth') {
            if (strtolower((string) $active->client_email) === strtolower((string) $ownerEmail)) {
                return;
            }
        }

        $owners = array_values(array_unique([
            ...($resource->getOwners() ?? []),
            $this->serviceAccountEmail(),
            $ownerEmail,
        ]));
        $resource->setOwners(array_values(array_filter($owners)));

        $this->call('add human property owner', $ownerEmail,
            fn () => $verification->webResource->update((string) $resource->getId(), $resource));
    }

    private function ownerEmail(): ?string
    {
        $value = $this->accounts->active()?->owner_email
            ?? config('services.google_search_console.owner_email');

        return filled($value) ? strtolower(trim((string) $value)) : null;
    }

    private function serviceAccountEmail(): string
    {
        $active = $this->accounts->active();
        if ($active !== null) {
            return $active->client_email;
        }

        $path = (string) config('services.google_search_console.credentials_path');
        $credentials = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];

        return is_array($credentials) ? (string) ($credentials['client_email'] ?? '') : '';
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function call(string $operation, string $domain, callable $callback): mixed
    {
        $startedAt = microtime(true);

        try {
            $result = $callback();
            $this->recordUsage($operation, 200, true, $startedAt);

            return $result;
        } catch (GoogleServiceException $exception) {
            $this->recordUsage($operation, (int) $exception->getCode(), false, $startedAt);
            $this->fail($operation, $domain, $exception);
        } catch (Throwable $exception) {
            $this->recordUsage($operation, 0, false, $startedAt);

            throw $exception;
        }
    }

    private function recordUsage(string $operation, int $statusCode, bool $successful, float $startedAt): void
    {
        $this->usageRecorder->record(
            provider: 'google_search_console',
            operation: $operation,
            method: 'POST',
            endpoint: 'https://googleapis.com/'.str_replace(' ', '-', $operation),
            statusCode: $statusCode > 0 ? $statusCode : null,
            successful: $successful,
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
        );
    }

    private function fail(string $operation, string $domain, GoogleServiceException $exception): never
    {
        Log::error('Google Search Console request failed.', [
            'operation' => $operation,
            'domain' => $domain,
            'status' => $exception->getCode(),
            'error' => $exception->getErrors()[0]['message'] ?? null,
        ]);

        throw new RuntimeException("Google Search Console failed to {$operation} for {$domain}.", 0, $exception);
    }

    private function hasTxtRecord(string $domain, string $token): bool
    {
        foreach ($this->dnsService->getDnsRecords($domain) as $record) {
            if (($record['type'] ?? '') === 'TXT' && trim((string) ($record['content'] ?? ''), '"') === $token) {
                return true;
            }
        }

        return false;
    }

    public function propertyUrl(string $domainOrUrl): string
    {
        $trimmed = trim($domainOrUrl);
        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            $parsed = parse_url($trimmed);
            $scheme = $parsed['scheme'] ?? 'https';
            $host = $parsed['host'] ?? $trimmed;
            $path = $parsed['path'] ?? '/';

            return "{$scheme}://{$host}".(str_ends_with($path, '/') ? $path : "{$path}/");
        }

        if (str_starts_with($trimmed, 'sc-domain:')) {
            return $trimmed;
        }

        $domain = $this->normalizeDomain($trimmed);

        return "sc-domain:{$domain}";
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = (string) preg_replace('#^https?://#', '', $domain);

        return rtrim(explode('/', $domain)[0], '.');
    }
}
