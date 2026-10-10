<?php

namespace App\Services\RunCloud;

use App\Services\Cloudflare\CloudflareDnsService;
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
    public const DEFAULT_PHP_VERSION = 'php81';

    protected CloudflareDnsService $cloudflareDnsService;

    protected string $baseUrl;

    protected ?string $apiKey;

    protected ?string $apiSecret;

    protected ?string $apiToken;

    protected int $timeout;

    public function __construct(?CloudflareDnsService $cloudflareDnsService = null)
    {
        $this->cloudflareDnsService = $cloudflareDnsService ?? app(CloudflareDnsService::class);
        $this->baseUrl = rtrim((string) Config::get('services.runcloud.base_url', 'https://manage.runcloud.io/api/v3'), '/');
        $this->apiKey = Config::get('services.runcloud.api_key');
        $this->apiSecret = Config::get('services.runcloud.api_secret');
        $this->apiToken = Config::get('services.runcloud.api_token');
        $this->timeout = (int) Config::get('services.runcloud.timeout', 30);
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
    public function createEmptyWebApplication(string $domain, ?int $serverId = null): array
    {
        $server = $serverId ?? (int) Config::get('services.runcloud.server_id');

        // If no server was provided or configured, auto-select the first server from your account:
        if (! $server) {
            $servers = $this->getServers();
            $server = (int) ($servers[0]['id'] ?? 0);

            if (! $server) {
                throw new Exception('No RunCloud server found in your account.');
            }
        }

        // Normalize domain (lowercase, remove any protocol or paths)
        $domain = $this->normalizeDomain($domain);

        // 1. Generate Web Application Name (Rule: No TLD, max 30 chars)
        $webAppName = $this->generateWebAppName($domain);

        Log::info('Initiating RunCloud Empty Web Application creation', [
            'server_id' => $server,
            'domain' => $domain,
            'web_app_name' => $webAppName,
            'php_version' => self::DEFAULT_PHP_VERSION,
        ]);

        // 2. Duplicate Check
        $existingApp = $this->findExistingWebApplication($server, $domain, $webAppName);
        if ($existingApp) {
            Log::warning('RunCloud Web Application already exists', [
                'server_id' => $server,
                'domain' => $domain,
                'app_id' => $existingApp['id'] ?? null,
            ]);

            return [
                'already_exists' => true,
                'message' => 'Web application with this domain or name already exists on RunCloud.',
                'app' => $existingApp,
            ];
        }

        // 3. Resolve existing system user "runcloud"
        $systemUserId = $this->getRunCloudSystemUserId($server);

        // 4. Create the Custom/Empty Web Application on PHP 8.1
        $createdApp = $this->createCustomWebApp($server, $webAppName, $domain, $systemUserId);
        $appId = (int) ($createdApp['id'] ?? 0);

        if (! $appId) {
            throw new Exception('RunCloud created web application but failed to return an ID.');
        }

        // 5. Configure Domain with WWW enabled and preferred redirection unset ("none") (No RunCloud DNS integration)
        $domainConfig = $this->configureDomain($server, $appId, $domain);

        // 6. Direct Cloudflare DNS Configuration: delete all existing records, then add @ and www A records for the RunCloud server
        $dnsSetup = null;
        try {
            $serverIp = $this->getServerIp($server);
            $dnsSetup = $this->cloudflareDnsService->setupRunCloudDns($domain, $serverIp);
        } catch (Exception $e) {
            Log::warning('Direct Cloudflare DNS setup was not applied during RunCloud web app creation', [
                'domain' => $domain,
                'server_id' => $server,
                'error' => $e->getMessage(),
            ]);
        }

        // 7. Configure Advanced SSL / AutoSSL
        $sslConfig = $this->configureAutoSsl($server, $appId);

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
            'ssl' => 'AutoSSL (Advanced)',
            'data' => $createdApp,
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
        $response = $this->client()->get('/servers');

        if (! $response->successful()) {
            $this->handleApiError('Failed to fetch RunCloud servers', $response);
        }

        return $response->json('data') ?? $response->json() ?? [];
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
        $response = $this->client()->get("/servers/{$serverId}");

        if (! $response->successful()) {
            $this->handleApiError("Failed to fetch RunCloud server {$serverId}", $response);
        }

        return $response->json('data') ?? $response->json() ?? [];
    }

    /**
     * Resolve the public IP address for a RunCloud server.
     *
     * @throws Exception
     */
    public function getServerIp(?int $serverId = null): string
    {
        // 1. Direct configuration override if provided
        $configuredIp = Config::get('services.runcloud.server_ip');
        if (! empty($configuredIp)) {
            return (string) $configuredIp;
        }

        $server = $serverId ?? (int) Config::get('services.runcloud.server_id');

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

        $serverDetails = $this->getServer($server);
        $ip = $serverDetails['ipAddress'] ?? $serverDetails['ip_address'] ?? $serverDetails['ip'] ?? null;

        if (empty($ip)) {
            throw new Exception("Unable to determine IP address for RunCloud server {$server}.");
        }

        return (string) $ip;
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
     */
    public function getRunCloudSystemUserId(int $serverId): int
    {
        $response = $this->client()->get("/servers/{$serverId}/system-users");

        if ($response->successful()) {
            $users = $response->json('data') ?? $response->json() ?? [];

            foreach ($users as $user) {
                if (strtolower($user['username'] ?? '') === 'runcloud') {
                    return (int) $user['id'];
                }
            }
        }

        // Fallback: 1 is the default primary system user ID for "runcloud"
        return 1;
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
            'stack' => 'native',
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
     * Rule 7: Configure Advanced SSL/TLS & AutoSSL.
     */
    protected function configureAutoSsl(int $serverId, int $appId): array
    {
        $payload = [
            'sslMethod' => 'advanced',
            'provider' => 'letsencrypt',
            'autoSsl' => true,
        ];

        $response = $this->client()->post("/servers/{$serverId}/webapps/{$appId}/ssl", $payload);

        return $response->json('data') ?? $response->json() ?? [];
    }

    /**
     * Prepared HTTP client with authentication and headers.
     */
    protected function client(): PendingRequest
    {
        $apiToken = $this->apiToken;
        $apiKey = $this->apiKey;
        $apiSecret = $this->apiSecret;

        // Fallback to database EnvironmentVariables
        if (empty($apiToken) && empty($apiKey)) {
            try {
                $dbToken = \App\Models\EnvironmentVariable::where('key', 'RUNCLOUD_API_TOKEN')->latest()->first();
                if ($dbToken && ! empty($dbToken->value)) {
                    $apiToken = $dbToken->value;
                } else {
                    $dbKey = \App\Models\EnvironmentVariable::where('key', 'RUNCLOUD_API_KEY')->latest()->first();
                    if ($dbKey && ! empty($dbKey->value)) {
                        $apiKey = $dbKey->value;
                    }
                }
            } catch (Exception) {
                // Ignore DB errors
            }
        }

        $client = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);

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
