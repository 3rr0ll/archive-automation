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
        $phpVersion = $this->webAppService->defaultPhpVersion();

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
            'php_version' => $phpVersion,
        ]);

        // 3. Resolve system user "runcloud" ID (Check EnvironmentVariable model / cache first to save 1 API call)
        $systemUserId = $this->serverService->getRunCloudSystemUserId($server);

        // 4. Create the Custom/Empty Web Application with the configured PHP version
        $createResponse = $this->webAppService->attemptCreateCustomWebApp($server, $webAppName, $domain, $systemUserId);

        if (! $createResponse['success']) {
            if ($createResponse['is_duplicate'] ?? false) {
                $existingApp = $this->webAppService->findExistingWebApplication(
                    $server,
                    $domain,
                    $webAppName,
                );

                Log::warning('RunCloud Web Application already exists (caught on creation)', [
                    'server_id' => $server,
                    'domain' => $domain,
                    'message' => $createResponse['message'] ?? '',
                ]);

                return [
                    'already_exists' => true,
                    'message' => 'Web application with this domain or name already exists on RunCloud.',
                    'web_app_id' => (int) ($existingApp['id'] ?? 0) ?: null,
                    'web_app_name' => $existingApp['name'] ?? $webAppName,
                    'app' => $existingApp ?? ($createResponse['data'] ?? []),
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
            ?? $createdApp['primary_domain_id']
            ?? $createdApp['domain_id']
            ?? 0
        ) ?: null;

        // If not present in the creation payload, resolve domain ID via single GET /domains
        if (! $domainId) {
            $domainId = $this->webAppService->getDomainId($server, $appId, $domain);
        }

        // 6. Configure Cloudflare DNS.
        $dnsSetup = null;
        $resolvedIp = $serverIp ?: $this->serverService->getServerIp($server);
        try {
            $dnsSetup = $this->cloudflareDnsService->setupRunCloudDns($domain, $resolvedIp, true);
        } catch (Exception $e) {
            Log::warning('Direct Cloudflare DNS setup was not applied during RunCloud web app creation', [
                'domain' => $domain,
                'server_id' => $server,
                'error' => $e->getMessage(),
            ]);
        }

        // 7. Configure Advanced SSL (Let's Encrypt dns-01 via Cloudflare externalApi + AutoSSL)
        $sslConfig = null;
        try {
            $sslConfig = $this->sslService->configureAdvancedSsl($server, $appId, $domain, $domainId);
        } catch (Exception $e) {
            Log::warning('RunCloud Advanced SSL configuration failed during empty web app creation', [
                'server_id' => $server,
                'web_app_id' => $appId,
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Successfully created RunCloud Empty Web Application', [
            'server_id' => $server,
            'web_app_id' => $appId,
            'web_app_name' => $webAppName,
            'domain' => $domain,
            'php_version' => $phpVersion,
        ]);

        return [
            'success' => true,
            'server_id' => $server,
            'web_app_id' => $appId,
            'web_app_name' => $webAppName,
            'domain' => $domain,
            'php_version' => $phpVersion,
            'public_path' => '/public_html',
            'www_enabled' => true,
            'preferred_domain' => 'unset (none)',
            'dns_integration' => 'cloudflare_direct',
            'dns_setup' => $dnsSetup,
            'ssl' => "Let's Encrypt (Advanced SSL / AutoSSL / dns-01)",
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

    public function resolveWebApplication(int $serverId, string $domain, string $appName): ?array
    {
        return $this->webAppService->resolveWebApplication($serverId, $domain, $appName);
    }

    public function findWebApplicationByDomain(int $serverId, string $domain): ?array
    {
        return $this->webAppService->findWebApplicationByDomain($serverId, $domain);
    }

    public function deleteWebApplication(int $serverId, int $webAppId): void
    {
        $this->webAppService->deleteWebApplication($serverId, $webAppId);
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

    public function configureDomain(int $serverId, int $appId, string $domain, ?int $domainId = null): array
    {
        return $this->webAppService->configureDomain($serverId, $appId, $domain, $domainId);
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
