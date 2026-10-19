<?php

namespace App\Services\RunCloud;

use App\Models\Deployment;
use App\Models\EnvironmentVariable;
use App\Models\Server;
use App\Services\Cache\ApplicationCache;
use App\Services\Cloudflare\CloudflareDnsService;
use App\Services\Http\ExternalHttpClient;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RunCloudService
{
    // Strictly PHP 8.1 for deployment
    public const DEFAULT_PHP_VERSION = 'php81rc';

    protected CloudflareDnsService $cloudflareDnsService;

    protected string $baseUrl;

    protected ?string $apiKey;

    protected ?string $apiSecret;

    protected ?string $apiToken;

    protected int $timeout;

    protected ApplicationCache $cache;

    protected ExternalHttpClient $httpClient;

    public function __construct(
        ?CloudflareDnsService $cloudflareDnsService = null,
        ?ApplicationCache $cache = null,
        ?ExternalHttpClient $httpClient = null,
    ) {
        $this->cloudflareDnsService = $cloudflareDnsService ?? app(CloudflareDnsService::class);
        $this->cache = $cache ?? app(ApplicationCache::class);
        $this->httpClient = $httpClient ?? app(ExternalHttpClient::class);
        $this->baseUrl = rtrim((string) Config::get('services.runcloud.base_url', 'https://manage.runcloud.io/api/v3'), '/');
        $this->apiKey = Config::get('services.runcloud.api_key');
        $this->apiSecret = Config::get('services.runcloud.api_secret');
        $this->apiToken = Config::get('services.runcloud.api_token');
        $this->timeout = (int) Config::get('services.runcloud.timeout', 30);
    }

    /**
     * Retrieve an environment variable value from DB first, falling back to Config/.env.
     */
    protected function getEnvValue(string $key, mixed $default = null): mixed
    {
        try {
            $record = EnvironmentVariable::where('key', $key)->latest('updated_at')->latest('id')->first();
            if ($record && ! empty($record->value)) {
                return $record->value;
            }
        } catch (Exception) {
            // DB not available or table not migrated yet
        }

        return $default;
    }

    /**
     * Create an Empty Web Application on RunCloud.
     * Note: $version is intentionally ignored and not used by RunCloud.
     *
     * @param  string  $domain  The root domain (e.g. "example.com")
     * @param  int|null  $serverId  RunCloud server ID (optional; falls back to config)
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function createEmptyWebApplication(string $domain, ?int $serverId = null, ?string $serverIp = null): array
    {
        // 1. Resolve Server ID: Use passed ID -> DB Server model -> EnvironmentVariable -> Config
        $serverModel = null;
        if ($serverId) {
            $serverModel = Server::where('server_id', $serverId)->first();
        }

        if (! $serverModel) {
            $defaultServerId = $this->getEnvValue('RUNCLOUD_DEFAULT_SERVER_ID')
                ?? $this->getEnvValue('RUNCLOUD_SERVER_ID')
                ?? Config::get('services.runcloud.server_id');

            if ($defaultServerId) {
                $serverModel = Server::where('server_id', (int) $defaultServerId)->first();
            }

            // Fallback to active server in local DB if available
            if (! $serverModel) {
                $serverModel = Server::where('is_active', true)->first() ?? Server::first();
            }
        }

        $server = $serverId ?? ($serverModel ? (int) $serverModel->server_id : null);

        // If still not resolved from local DB or config, query RunCloud API as last resort
        if (! $server) {
            $servers = $this->getServers();
            $server = (int) ($servers[0]['id'] ?? 0);

            if (! $server) {
                throw new Exception('No RunCloud server found in your account.');
            }
        }

        // Normalize domain (lowercase, remove any protocol or paths)
        $domain = $this->normalizeDomain($domain);

        // Generate Web Application Name (Rule: No TLD, max 30 chars)
        $webAppName = $this->generateWebAppName($domain);

        // 2. Local duplicate check first (0 API requests)
        $localDeployment = Deployment::where('domain', $domain)->first();
        if ($localDeployment && $localDeployment->status?->value === 'completed') {
            Log::info('Local duplicate check: domain already deployed according to database', ['domain' => $domain]);
        }

        Log::info('Initiating RunCloud Empty Web Application creation', [
            'server_id' => $server,
            'domain' => $domain,
            'web_app_name' => $webAppName,
            'php_version' => self::DEFAULT_PHP_VERSION,
        ]);

        // 3. Resolve system user "runcloud" ID (Check EnvironmentVariable model / cache first to save 1 API call)
        $systemUserId = $this->getRunCloudSystemUserId($server);

        // 4. Create the Custom/Empty Web Application on PHP 8.1
        // Handles external/manual RunCloud creations optimistically: catches HTTP 422 if domain or name is already taken
        $createResponse = $this->attemptCreateCustomWebApp($server, $webAppName, $domain, $systemUserId);

        if (! $createResponse['success']) {
            if ($createResponse['is_duplicate'] ?? false) {
                Log::warning('RunCloud Web Application already exists (caught on creation)', [
                    'server_id' => $server,
                    'domain' => $domain,
                    'message' => $createResponse['message'] ?? '',
                ]);

                return [
                    'already_exists' => true,
                    'message' => 'Web application with this domain or name already exists on RunCloud.',
                    'app' => $createResponse['data'] ?? [],
                ];
            }

            throw new Exception('RunCloud create webapp failed: '.($createResponse['message'] ?? 'Unknown error'));
        }

        $createdApp = $createResponse['data'];
        $appId = (int) ($createdApp['id'] ?? 0);

        if (! $appId) {
            throw new Exception('RunCloud created web application but failed to return an ID.');
        }

        // Note: RunCloud automatically registers the primary domain upon creating custom webapp.
        // We do NOT call POST /domains here to prevent a redundant API call and 422 errors.

        // 5. Direct Cloudflare DNS Configuration: delete all existing records, then add @ and www A records for the RunCloud server
        // Note: Set proxied=false so Let's Encrypt http-01 challenge can reach the server directly without being blocked
        $dnsSetup = null;
        try {
            $resolvedIp = $serverIp ?: $this->getServerIp($server);
            $dnsSetup = $this->cloudflareDnsService->setupRunCloudDns($domain, $resolvedIp, false);
        } catch (Exception $e) {
            Log::warning('Direct Cloudflare DNS setup was not applied during RunCloud web app creation', [
                'domain' => $domain,
                'server_id' => $server,
                'error' => $e->getMessage(),
            ]);
        }

        // 6. Configure Advanced SSL / AutoSSL
        $sslConfig = $this->configureAdvancedSsl($server, $appId, $domain);

        Log::info('Successfully created RunCloud Empty Web Application', [
            'server_id' => $server,
            'web_app_id' => $appId,
            'web_app_name' => $webAppName,
            'domain' => $domain,
            'php_version' => self::DEFAULT_PHP_VERSION,
        ]);

        return [
            'success' => true,
            'server_id' => $server,
            'web_app_id' => $appId,
            'web_app_name' => $webAppName,
            'domain' => $domain,
            'php_version' => '8.1',
            'public_path' => '/public_html',
            'www_enabled' => true,
            'preferred_domain' => 'unset (none)',
            'dns_integration' => 'cloudflare_direct',
            'dns_setup' => $dnsSetup,
            'ssl' => "Let's Encrypt (Advanced SSL / HTTP-01)",
            'ssl_config' => $sslConfig,
            'data' => $createdApp,
        ];
    }

    /**
     * Attempt to create custom web app, detecting 422 duplicate/conflict responses without an upfront GET.
     */
    protected function attemptCreateCustomWebApp(int $serverId, string $webAppName, string $domain, int $systemUserId): array
    {
        $payload = [
            'name' => $webAppName,
            'domainName' => $domain,
            'user' => $systemUserId,
            'publicPath' => '/public_html',
            'phpVersion' => self::DEFAULT_PHP_VERSION, // Strictly PHP 8.1
            'stack' => 'hybrid',
            'timezone' => 'UTC',
        ];

        $response = $this->client()->post("/servers/{$serverId}/webapps/custom", $payload);

        if ($response->status() === 422) {
            $responseBody = $response->json();
            $message = json_encode($responseBody);

            $isDuplicate = str_contains(strtolower($message), 'already been taken')
                || str_contains(strtolower($message), 'already taken')
                || str_contains(strtolower($message), 'already exists')
                || str_contains(strtolower($message), 'has already been used');

            if ($isDuplicate) {
                return [
                    'success' => false,
                    'is_duplicate' => true,
                    'message' => $responseBody['message'] ?? 'Web application or domain already exists on RunCloud.',
                    'data' => $responseBody,
                ];
            }
        }

        if (! $response->successful()) {
            $this->handleApiError('RunCloud create webapp failed', $response);
        }

        return [
            'success' => true,
            'is_duplicate' => false,
            'data' => $response->json('data') ?? $response->json() ?? [],
        ];
    }

    /**
     * Get all connected servers from the RunCloud account.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getServers(): array
    {
        return $this->cache->rememberRunCloudServers(function (): array {
            $response = $this->client()->get('/servers');

            if (! $response->successful()) {
                $this->handleApiError('Failed to fetch RunCloud servers', $response);
            }

            return $response->json('data') ?? $response->json() ?? [];
        });
    }

    /**
     * Get details of a specific RunCloud server.
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function getServer(int $serverId): array
    {
        return $this->cache->rememberRunCloudServer($serverId, function () use ($serverId): array {
            $response = $this->client()->get("/servers/{$serverId}");

            if (! $response->successful()) {
                $this->handleApiError("Failed to fetch RunCloud server {$serverId}", $response);
            }

            return $response->json('data') ?? $response->json() ?? [];
        });
    }

    /**
     * Resolve the public IP address for a RunCloud server.
     * Uses Server model first (0 API credits), then EnvironmentVariable, then Config, with caching.
     *
     * @throws Exception
     */
    public function getServerIp(?int $serverId = null): string
    {
        // 1. Check local Server model (0 API credits)
        if ($serverId) {
            $serverModel = Server::where('server_id', $serverId)->first();
            if ($serverModel && ! empty($serverModel->ip_address)) {
                return (string) $serverModel->ip_address;
            }
        }

        // 2. Direct configuration override if provided (EnvironmentVariable first, then Config)
        $configuredIp = $this->getEnvValue('RUNCLOUD_DEFAULT_SERVER_IP')
            ?? $this->getEnvValue('RUNCLOUD_SERVER_IP')
            ?? Config::get('services.runcloud.server_ip');

        if (! empty($configuredIp)) {
            return (string) $configuredIp;
        }

        // 3. Fallback to active server model in DB
        $activeServer = Server::where('is_active', true)->first() ?? Server::first();
        if ($activeServer && ! empty($activeServer->ip_address)) {
            return (string) $activeServer->ip_address;
        }

        $defaultServerId = $this->getEnvValue('RUNCLOUD_DEFAULT_SERVER_ID')
            ?? $this->getEnvValue('RUNCLOUD_SERVER_ID')
            ?? Config::get('services.runcloud.server_id');

        $server = $serverId ?? ($defaultServerId ? (int) $defaultServerId : null);

        // 4. If still not found, check cache or query RunCloud API as last resort
        if (! $server) {
            $servers = $this->getServers();
            $serverData = $servers[0] ?? [];
            $ip = $serverData['ipAddress'] ?? $serverData['ip_address'] ?? $serverData['ip'] ?? null;
            if (! empty($ip)) {
                return (string) $ip;
            }
            $server = (int) ($serverData['id'] ?? 0);
        }

        if (! $server) {
            throw new Exception('No RunCloud server ID configured or found.');
        }

        return $this->cache->rememberRunCloudServerIp($server, function () use ($server): string {
            $serverDetails = $this->getServer($server);
            $ip = $serverDetails['ipAddress'] ?? $serverDetails['ip_address'] ?? $serverDetails['ip'] ?? null;

            if (empty($ip)) {
                throw new Exception("Unable to determine IP address for RunCloud server {$server}.");
            }

            return (string) $ip;
        });
    }

    /**
     * Rule 1: Generate RunCloud Web Application Name.
     * - Removes TLD (.com, .net, etc.)
     * - Max 30 characters
     */
    public function generateWebAppName(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = explode('/', $domain)[0];
        $domain = explode(':', $domain)[0];

        $parts = explode('.', $domain);
        $nameWithoutTld = $parts[0] ?? $domain;

        $cleanName = preg_replace('/[^a-z0-9_-]/', '', $nameWithoutTld) ?? '';

        if ($cleanName === '') {
            $cleanName = 'app'.Str::random(6);
        }

        return substr($cleanName, 0, 30);
    }

    /**
     * Normalize domain string to ensure no version prefixes or paths leak into RunCloud.
     */
    public function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = explode('/', $domain)[0];
        $domain = explode(':', $domain)[0];

        if (str_starts_with($domain, 'www.')) {
            $domain = substr($domain, 4);
        }

        return $domain;
    }

    /**
     * Check if domain or application name already exists on the server.
     */
    public function findExistingWebApplication(int $serverId, string $domain, string $appName): ?array
    {
        try {
            $response = $this->client()->get("/servers/{$serverId}/webapps");

            if ($response->successful()) {
                $apps = $response->json('data') ?? $response->json() ?? [];

                foreach ($apps as $app) {
                    $existingName = strtolower($app['name'] ?? '');
                    $existingDomain = strtolower($app['domainName'] ?? '');

                    if ($existingName === strtolower($appName) || $existingDomain === strtolower($domain)) {
                        return $app;
                    }
                }
            }
        } catch (Exception $e) {
            Log::warning('RunCloud duplicate check failed to fetch webapps list: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Rule 2: Get ID of existing system user "runcloud".
     * Checks EnvironmentVariable (RUNCLOUD_USER_ID) and Cache first (0 API requests).
     */
    public function getRunCloudSystemUserId(int $serverId): int
    {
        // 1. Check EnvironmentVariable model or Config first (0 API calls)
        $configuredUserId = $this->getEnvValue('RUNCLOUD_USER_ID')
            ?? $this->getEnvValue('RUNCLOUD_SYSTEM_USER_ID')
            ?? Config::get('services.runcloud.user_id');

        if (! empty($configuredUserId)) {
            return (int) $configuredUserId;
        }

        // 2. Cache the result if fetched from API so it only ever calls once across all deployments
        return $this->cache->rememberRunCloudSystemUserId($serverId, function () use ($serverId): int {
            $response = $this->client()->get("/servers/{$serverId}/users");

            if (! $response->successful()) {
                $this->handleApiError("Failed to fetch RunCloud system users for server {$serverId}", $response);
            }

            $users = $response->json('data') ?? $response->json() ?? [];

            foreach ($users as $user) {
                if (strtolower($user['username'] ?? '') === 'runcloud') {
                    return (int) $user['id'];
                }
            }

            throw new Exception("RunCloud system user 'runcloud' was not found on server {$serverId}.");
        });
    }

    /**
     * Create the Empty/Custom Web Application with PHP 8.1 and public path /public_html.
     */
    protected function createCustomWebApp(int $serverId, string $webAppName, string $domain, int $systemUserId): array
    {
        $payload = [
            'name' => $webAppName,
            'domainName' => $domain,
            'user' => $systemUserId,
            'publicPath' => '/public_html',
            'phpVersion' => self::DEFAULT_PHP_VERSION, // Strictly PHP 8.1
            'stack' => 'hybrid',
            'timezone' => 'UTC',
        ];

        $response = $this->client()->post("/servers/{$serverId}/webapps/custom", $payload);

        if (! $response->successful()) {
            $this->handleApiError('RunCloud create webapp failed', $response);
        }

        return $response->json('data') ?? $response->json() ?? [];
    }

    /**
     * Rule 3, 4, 5: Configure Domain, enable WWW, unset preferred redirection (no RunCloud DNS integration).
     */
    protected function configureDomain(int $serverId, int $appId, string $domain): array
    {
        $payload = [
            'name' => $domain,
            'www' => true,                       // Rule 4: Enable WWW
            'redirection' => 'none',             // Rule 5: Preferred domain version unset
            'type' => 'primary',
        ];

        $response = $this->client()->post("/servers/{$serverId}/webapps/{$appId}/domains", $payload);

        if ($response->status() === 422 || ! $response->successful()) {
            Log::info('Domain attach returned non-200, verifying existing domain configuration', [
                'status' => $response->status(),
            ]);
        }

        return $response->json('data') ?? $response->json() ?? [];
    }

    /**
     * Configure RunCloud Advanced SSL (Domain SSL) for the web application and its domain.
     */
    public function configureAdvancedSsl(int $serverId, int $appId, string $domain): array
    {
        // 1. Enable Advanced SSL & AutoSSL mode on the web application
        $this->enableAdvancedSslMode($serverId, $appId);

        // 2. Fetch the domain ID registered under this web app
        $domainId = $this->getDomainId($serverId, $appId, $domain);

        if (! $domainId) {
            Log::warning('RunCloud Advanced SSL could not find domain ID, falling back to Basic SSL', [
                'server_id' => $serverId,
                'app_id' => $appId,
                'domain' => $domain,
            ]);

            return $this->configureAutoSsl($serverId, $appId);
        }

        // 3. Deploy Let's Encrypt to the domain under Advanced SSL
        $payload = [
            'provider' => 'letsencrypt',
            'authorizationMethod' => 'http-01',
            'environment' => 'live',
            'enableHttp' => false, // Redirect plain HTTP to HTTPS
            'enableHsts' => false,
        ];

        $response = $this->client()->post("/servers/{$serverId}/webapps/{$appId}/domains/{$domainId}/ssl", $payload);

        if (! $response->successful()) {
            Log::warning('RunCloud Advanced Domain SSL installation returned non-200, attempting fallback', [
                'server_id' => $serverId,
                'app_id' => $appId,
                'domain_id' => $domainId,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            return $this->configureAutoSsl($serverId, $appId);
        }

        Log::info('RunCloud Advanced Domain SSL successfully queued', [
            'server_id' => $serverId,
            'app_id' => $appId,
            'domain_id' => $domainId,
            'response' => $response->json(),
        ]);

        return $response->json('data') ?? $response->json() ?? [];
    }

    /**
     * Enable Advanced SSL and AutoSSL mode on a web application.
     */
    public function enableAdvancedSslMode(int $serverId, int $appId): bool
    {
        try {
            $response = $this->client()->post("/servers/{$serverId}/webapps/{$appId}/ssl/advanced", [
                'advancedSSL' => true,
                'autoSSL' => true,
            ]);

            return $response->successful();
        } catch (Exception $e) {
            Log::warning('Failed to enable Advanced SSL mode on webapp: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Find the domain ID for a web application domain.
     */
    public function getDomainId(int $serverId, int $appId, string $domain): ?int
    {
        try {
            $response = $this->client()->get("/servers/{$serverId}/webapps/{$appId}/domains");

            if ($response->successful()) {
                $domains = $response->json('data') ?? $response->json() ?? [];
                $normalized = strtolower(trim($domain));

                foreach ($domains as $item) {
                    $itemDomain = strtolower(trim($item['name'] ?? ''));
                    if ($itemDomain === $normalized || str_contains($itemDomain, $normalized)) {
                        return (int) $item['id'];
                    }
                }

                // If not matched by name, return the first/primary domain ID if available
                if (! empty($domains[0]['id'])) {
                    return (int) $domains[0]['id'];
                }
            }
        } catch (Exception $e) {
            Log::warning('Failed to retrieve domains list for webapp: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Configure standard Let's Encrypt SSL via HTTP-01 challenge (fallback).
     */
    protected function configureAutoSsl(int $serverId, int $appId): array
    {
        $payload = [
            'provider' => 'letsencrypt',
            'authorizationMethod' => 'http-01',
            'environment' => 'live',
            'enableHttp' => false, // Redirect plain HTTP to HTTPS
            'enableHsts' => false,
            'ssl_protocol_id' => 1,
        ];

        $response = $this->client()->post("/servers/{$serverId}/webapps/{$appId}/ssl", $payload);

        if (! $response->successful()) {
            Log::warning('RunCloud SSL installation returned non-200', [
                'server_id' => $serverId,
                'app_id' => $appId,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);
        }

        return $response->json('data') ?? $response->json() ?? [];
    }

    /**
     * Prepared HTTP client with authentication and headers.
     */
    protected function client(): PendingRequest
    {
        // Prioritize database EnvironmentVariables as primary source of truth
        $apiToken = $this->getEnvValue('RUNCLOUD_API_TOKEN', $this->apiToken);
        $apiKey = $this->getEnvValue('RUNCLOUD_API_KEY', $this->apiKey);
        $apiSecret = $this->getEnvValue('RUNCLOUD_API_SECRET', $this->apiSecret);

        $client = $this->httpClient->configure(
            Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]),
            'runcloud',
        );

        // Bearer Token Authentication (Workspaces / v3 / Single API Key)
        if (! empty($apiToken)) {
            return $client->withToken($apiToken);
        }

        // If only API Key is provided (without secret), use it as Bearer token
        if (! empty($apiKey) && empty($apiSecret)) {
            return $client->withToken($apiKey);
        }

        // Basic Authentication (API Key : API Secret for legacy v2)
        if (! empty($apiKey) && ! empty($apiSecret)) {
            return $client->withBasicAuth($apiKey, $apiSecret);
        }

        throw new Exception('RunCloud credentials not configured. Please add RUNCLOUD_API_KEY or RUNCLOUD_API_TOKEN in your Environment Variables UI or .env file.');
    }

    /**
     * Handle and normalize API errors without leaking secrets.
     */
    protected function handleApiError(string $context, Response $response): void
    {
        $statusCode = $response->status();
        $errorBody = $response->json();
        $message = $errorBody['message'] ?? $errorBody['error'] ?? $response->body();

        Log::error("{$context}: HTTP {$statusCode}", [
            'status' => $statusCode,
            'message' => $message,
        ]);

        throw new Exception("RunCloud API error ({$statusCode}): {$message}");
    }
}
