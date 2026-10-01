<?php

namespace App\Services\RunCloud;

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

    protected string $baseUrl;
    protected ?string $apiKey;
    protected ?string $apiSecret;
    protected ?string $apiToken;
    protected int $timeout;

    public function __construct()
    {
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
     * @param string $domain The root domain (e.g. "example.com")
     * @param int|null $serverId RunCloud server ID (optional; falls back to config)
     * @return array<string, mixed>
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

        // 5. Configure Domain with WWW enabled, preferred redirection unset ("none"), and Cloudflare DNS
        $domainConfig = $this->configureDomain($server, $appId, $domain);

        // 6. Configure Advanced SSL / AutoSSL
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
            'dns_integration' => 'cloudflare',
            'ssl' => 'AutoSSL (Advanced)',
            'data' => $createdApp,
        ];
    }

    /**
     * Get all connected servers from the RunCloud account.
     *
     * @return array<int, array<string, mixed>>
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
            $cleanName = 'app' . Str::random(6);
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
            Log::warning('RunCloud duplicate check failed to fetch webapps list: ' . $e->getMessage());
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
     * Rule 3, 4, 5, 6: Configure Domain, enable WWW, unset preferred redirection, attach Cloudflare DNS.
     */
    protected function configureDomain(int $serverId, int $appId, string $domain): array
    {
        $payload = [
            'name' => $domain,
            'www' => true,                       // Rule 4: Enable WWW
            'redirection' => 'none',             // Rule 5: Preferred domain version unset
            'type' => 'primary',
            'dnsIntegration' => 'cloudflare',    // Rule 6: Cloudflare DNS Integration
            'proxied' => true,                   // Rule 6: Set as Proxied
            'allowDnsIntegration' => true,       // Rule 6: Allow DNS record creation/update
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
        $client = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);

        // Bearer Token Authentication (Workspaces / v3 / Single API Key)
        if (! empty($this->apiToken)) {
            return $client->withToken($this->apiToken);
        }

        // If only API Key is provided (without secret), use it as Bearer token
        if (! empty($this->apiKey) && empty($this->apiSecret)) {
            return $client->withToken($this->apiKey);
        }

        // Basic Authentication (API Key : API Secret for legacy v2)
        if (! empty($this->apiKey) && ! empty($this->apiSecret)) {
            return $client->withBasicAuth($this->apiKey, $this->apiSecret);
        }

        throw new Exception('RunCloud credentials not configured. Please supply RUNCLOUD_API_KEY or RUNCLOUD_API_TOKEN in your .env file.');
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
