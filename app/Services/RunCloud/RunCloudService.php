<?php

namespace App\Services\RunCloud;

use App\Models\Deployment;
use App\Models\Server;
use App\Services\Cloudflare\CloudflareDnsService;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Unified RunCloud facade and deployment coordinator.
 * Delegates HTTP/Auth to RunCloudClient, server operations to RunCloudServerService,
 * webapp operations to RunCloudWebAppService, and SSL operations to RunCloudSslService.
 */
class RunCloudService
{
    public const DEFAULT_PHP_VERSION = RunCloudWebAppService::DEFAULT_PHP_VERSION;

    public function __construct(
        protected RunCloudClient $client,
        protected RunCloudServerService $serverService,
        protected RunCloudWebAppService $webAppService,
        protected RunCloudSslService $sslService,
        protected CloudflareDnsService $cloudflareDnsService
    ) {}

    // --- High-level Orchestration ---

    /**
     * Create an Empty Web Application on RunCloud and configure DNS & SSL.
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
            $defaultServerId = $this->client->getEnvValue('RUNCLOUD_DEFAULT_SERVER_ID')
                ?? $this->client->getEnvValue('RUNCLOUD_SERVER_ID')
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
            $servers = $this->serverService->getServers();
            $server = (int) ($servers[0]['id'] ?? 0);

            if (! $server) {
                throw new Exception('No RunCloud server found in your account.');
            }
        }

        // Normalize domain (lowercase, remove any protocol or paths)
        $domain = $this->webAppService->normalizeDomain($domain);

        // Generate Web Application Name (Rule: No TLD, max 30 chars)
        $webAppName = $this->webAppService->generateWebAppName($domain);

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
        $systemUserId = $this->serverService->getRunCloudSystemUserId($server);

        // 4. Create the Custom/Empty Web Application on PHP 8.1
        $createResponse = $this->webAppService->attemptCreateCustomWebApp($server, $webAppName, $domain, $systemUserId);

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

        // Extract primary domain ID if returned by RunCloud on app creation (avoids GET /domains)
        $domainId = (int) (
            $createdApp['primaryDomain']['id']
            ?? $createdApp['domains'][0]['id']
            ?? $createdApp['domain_id']
            ?? 0
        ) ?: null;

        // 5. Configure Cloudflare DNS.
        // For HTTP-01 challenges, create A records unproxied (grey cloud) so Let's Encrypt can
        // immediately reach the origin server without Cloudflare proxy delay or verification failures.
        $authMethod = (string) $this->client->getEnvValue('RUNCLOUD_SSL_AUTH_METHOD', Config::get('services.runcloud.ssl_authorization_method', 'http-01'));
        $initialProxyStatus = ($authMethod === 'dns-01');

        $dnsSetup = null;
        $resolvedIp = $serverIp ?: $this->serverService->getServerIp($server);
        try {
            $dnsSetup = $this->cloudflareDnsService->setupRunCloudDns($domain, $resolvedIp, $initialProxyStatus);
        } catch (Exception $e) {
            // For HTTP-01 challenges, DNS must succeed; fail immediately rather than attempting SSL without DNS
            if (! $initialProxyStatus) {
                throw new Exception("Cloudflare DNS setup failed for {$domain}: {$e->getMessage()}", 0, $e);
            }

            Log::warning('Direct Cloudflare DNS setup was not applied during RunCloud web app creation', [
                'domain' => $domain,
                'server_id' => $server,
                'error' => $e->getMessage(),
            ]);
        }

        // Stop if DNS propagation fails for HTTP-01
        if (! $initialProxyStatus) {
            $dnsReady = $this->sslService->waitForDnsPropagation($domain, $resolvedIp);

            if (! $dnsReady) {
                throw new Exception("DNS for {$domain} has not propagated to {$resolvedIp}. SSL was not requested.");
            }
        }

        // 6. Configure Advanced SSL / AutoSSL (passing domainId skips GET /domains if already present)
        $sslConfig = $this->sslService->configureAdvancedSsl($server, $appId, $domain, $domainId);

        // 7. If DNS was initialized unproxied for HTTP-01 validation, only switch Cloudflare proxy back ON
        // once SSL is confirmed issued by RunCloud.
        if (! $initialProxyStatus) {
            $domainId = $domainId ?? $this->webAppService->getDomainId($server, $appId, $domain);
            $sslIssued = $this->sslService->waitForSslIssued($server, $appId, $domain, $domainId);

            if (! $sslIssued) {
                throw new Exception(
                    "SSL issuance was not confirmed for {$domain}. Cloudflare proxy remains disabled for HTTP-01 validation."
                );
            }

            try {
                $this->cloudflareDnsService->setProxyStatus($domain, true);
                if (is_array($dnsSetup)) {
                    $dnsSetup['proxied'] = true;
                }
            } catch (Exception $e) {
                Log::warning('Failed to re-enable Cloudflare proxy status after SSL initiation', [
                    'domain' => $domain,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Successfully created RunCloud Empty Web Application', [
            'server_id' => $server,
            'web_app_id' => $appId,
            'web_app_name' => $webAppName,
            'domain' => $domain,
            'php_version' => self::DEFAULT_PHP_VERSION,
        ]);

        $sslAuthLabel = strtoupper($authMethod);

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
            'ssl' => "Let's Encrypt (Advanced SSL / {$sslAuthLabel})",
            'ssl_config' => $sslConfig,
            'data' => $createdApp,
        ];
    }

    // --- Server Delegations ---

    public function getServers(): array
    {
        return $this->serverService->getServers();
    }

    public function getServer(int $serverId): array
    {
        return $this->serverService->getServer($serverId);
    }

    public function getServerIp(?int $serverId = null): string
    {
        return $this->serverService->getServerIp($serverId);
    }

    public function getRunCloudSystemUserId(int $serverId): int
    {
        return $this->serverService->getRunCloudSystemUserId($serverId);
    }

    // --- WebApp Delegations ---

    public function generateWebAppName(string $domain): string
    {
        return $this->webAppService->generateWebAppName($domain);
    }

    public function normalizeDomain(string $domain): string
    {
        return $this->webAppService->normalizeDomain($domain);
    }

    public function findExistingWebApplication(int $serverId, string $domain, string $appName): ?array
    {
        return $this->webAppService->findExistingWebApplication($serverId, $domain, $appName);
    }

    public function attemptCreateCustomWebApp(int $serverId, string $webAppName, string $domain, int $systemUserId): array
    {
        return $this->webAppService->attemptCreateCustomWebApp($serverId, $webAppName, $domain, $systemUserId);
    }

    public function createCustomWebApp(int $serverId, string $webAppName, string $domain, int $systemUserId): array
    {
        return $this->webAppService->createCustomWebApp($serverId, $webAppName, $domain, $systemUserId);
    }

    public function customWebAppPayload(string $webAppName, string $domain, int $systemUserId): array
    {
        return $this->webAppService->customWebAppPayload($webAppName, $domain, $systemUserId);
    }

    public function configureDomain(int $serverId, int $appId, string $domain): array
    {
        return $this->webAppService->configureDomain($serverId, $appId, $domain);
    }

    public function getDomainId(int $serverId, int $appId, string $domain): ?int
    {
        return $this->webAppService->getDomainId($serverId, $appId, $domain);
    }

    // --- SSL Delegations ---

    public function configureAdvancedSsl(int $serverId, int $appId, string $domain, ?int $domainId = null): array
    {
        return $this->sslService->configureAdvancedSsl($serverId, $appId, $domain, $domainId);
    }

    public function enableAdvancedSslMode(int $serverId, int $appId): bool
    {
        return $this->sslService->enableAdvancedSslMode($serverId, $appId);
    }

    public function configureAutoSsl(int $serverId, int $appId): array
    {
        return $this->sslService->configureAutoSsl($serverId, $appId);
    }

    public function waitForDnsPropagation(string $domain, string $expectedIp, int $maxAttempts = 10, int $sleepSeconds = 3): bool
    {
        return $this->sslService->waitForDnsPropagation($domain, $expectedIp, $maxAttempts, $sleepSeconds);
    }

    public function waitForSslIssued(int $serverId, int $appId, string $domain, ?int $domainId = null, int $maxAttempts = 30, int $sleepSeconds = 5): bool
    {
        return $this->sslService->waitForSslIssued($serverId, $appId, $domain, $domainId, $maxAttempts, $sleepSeconds);
    }

    // --- Client / HTTP Delegations ---

    public function client(): PendingRequest
    {
        return $this->client->client();
    }

    public function handleApiError(string $context, Response $response): void
    {
        $this->client->handleApiError($context, $response);
    }

    public function getEnvValue(string $key, mixed $default = null): mixed
    {
        return $this->client->getEnvValue($key, $default);
    }
}
