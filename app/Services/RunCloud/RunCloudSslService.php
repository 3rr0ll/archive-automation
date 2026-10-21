<?php

namespace App\Services\RunCloud;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class RunCloudSslService
{
    public function __construct(
        protected RunCloudClient $client,
        protected RunCloudWebAppService $webAppService
    ) {}

    /**
     * Configure RunCloud Advanced SSL with AutoSSL for a domain.
     * Matches the RunCloud dashboard flow:
     * 1. Enable Advanced SSL & AutoSSL on the web application.
     * 2. Deploy Let's Encrypt certificate to the domain using dns-01 via Cloudflare externalApi.
     *
     * @param  int  $serverId
     * @param  int  $appId
     * @param  string  $domain
     * @param  int|null  $domainId
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function configureAdvancedSsl(int $serverId, int $appId, string $domain, ?int $domainId = null): array
    {
        // 1. Enable Advanced SSL and AutoSSL mode on the web application
        $this->enableAdvancedSslMode($serverId, $appId);

        // 2. Resolve domain ID under this web application if not provided
        $domainId = $domainId ?? $this->webAppService->getDomainId($serverId, $appId, $domain);

        if (! $domainId) {
            Log::error('RunCloud Advanced SSL could not find domain ID', [
                'server_id' => $serverId,
                'app_id' => $appId,
                'domain' => $domain,
            ]);

            throw new Exception("RunCloud could not find domain [{$domain}] to configure Advanced SSL.");
        }

        // 3. Resolve Cloudflare External API integration ID
        $externalApiId = $this->client->getEnvValue(
            'RUNCLOUD_CLOUDFLARE_EXTERNAL_API_ID',
            Config::get('services.runcloud.cloudflare_external_api_id')
        );

        if (! is_numeric($externalApiId) || (int) $externalApiId <= 0) {
            throw new Exception('RunCloud Cloudflare External API ID is not configured. Set RUNCLOUD_CLOUDFLARE_EXTERNAL_API_ID in your environment.');
        }

        // 4. Build payload matching RunCloud DNS-01 configuration:
        // - Authorization: dns-01
        // - Provider API Key: Cloudflare (externalApi ID)
        // - RSA Key Size: 2048-bit
        // - Environment: Live
        // - HTTP to HTTPS Redirection: Server Side HTTPS Redirection (enableHttp = false)
        $payload = [
            'provider' => 'letsencrypt',
            'authorizationMethod' => 'dns-01',
            'externalApi' => (int) $externalApiId,
            'environment' => 'live',
            'rsa_key_size' => 2048,
            'enableHttp' => false,
            'enableHsts' => false,
        ];

        Log::info('Deploying RunCloud Advanced SSL certificate', [
            'server_id' => $serverId,
            'app_id' => $appId,
            'domain_id' => $domainId,
            'domain' => $domain,
            'external_api' => (int) $externalApiId,
        ]);

        $response = $this->client->client()->post("/servers/{$serverId}/webapps/{$appId}/domains/{$domainId}/ssl", $payload);

        // If certificate endpoint already initialized or requires update, fall back to PUT
        if ($response->status() === 404 || $response->status() === 405) {
            $response = $this->client->client()->put("/servers/{$serverId}/webapps/{$appId}/domains/{$domainId}/ssl", $payload);
        }

        if (! $response->successful()) {
            Log::error('RunCloud rejected Advanced SSL certificate deployment', [
                'server_id' => $serverId,
                'app_id' => $appId,
                'domain_id' => $domainId,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            $this->client->handleApiError('RunCloud Advanced SSL certificate deployment failed', $response);
        }

        Log::info('RunCloud Advanced SSL certificate successfully deployed', [
            'server_id' => $serverId,
            'app_id' => $appId,
            'domain_id' => $domainId,
            'domain' => $domain,
        ]);

        return $response->json('data') ?? $response->json() ?? [];
    }

    /**
     * Enable Advanced SSL and AutoSSL mode on a web application.
     */
    public function enableAdvancedSslMode(int $serverId, int $appId): bool
    {
        try {
            $response = $this->client->client()->post("/servers/{$serverId}/webapps/{$appId}/ssl/advanced", [
                'advancedSSL' => true,
                'autoSSL' => true,
            ]);

            if (! $response->successful()) {
                Log::warning('RunCloud enable Advanced SSL with AutoSSL returned non-200', [
                    'server_id' => $serverId,
                    'app_id' => $appId,
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);
            }

            return $response->successful();
        } catch (Exception $e) {
            Log::warning('Failed to enable Advanced SSL mode on webapp: '.$e->getMessage(), [
                'server_id' => $serverId,
                'app_id' => $appId,
            ]);

            return false;
        }
    }
}
