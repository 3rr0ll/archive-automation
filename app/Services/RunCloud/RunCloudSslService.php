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
     * Configure RunCloud Advanced SSL (Domain SSL) for the web application and its domain.
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
        // 1. Enable Advanced SSL and AutoSSL on the web application.
        if (! $this->enableAdvancedSslMode($serverId, $appId)) {
            throw new Exception('RunCloud did not enable Advanced SSL with AutoSSL for the web application.');
        }

        // 2. Fetch the domain ID registered under this web app only if not already provided
        $domainId = $domainId ?? $this->webAppService->getDomainId($serverId, $appId, $domain);

        if (! $domainId) {
            Log::error('RunCloud Advanced SSL could not find the web application domain', [
                'server_id' => $serverId,
                'app_id' => $appId,
                'domain' => $domain,
            ]);

            throw new Exception("RunCloud could not find domain {$domain} while configuring Advanced SSL.");
        }

        // Check if an active certificate is already installed for this domain/app
        $existingSsl = $this->getActiveSsl($serverId, $appId, $domainId);
        if ($existingSsl !== null) {
            Log::info('RunCloud SSL certificate is already active, reusing existing certificate', [
                'server_id' => $serverId,
                'app_id' => $appId,
                'domain_id' => $domainId,
                'domain' => $domain,
            ]);

            return $existingSsl;
        }

        $authMethod = (string) $this->client->getEnvValue('RUNCLOUD_SSL_AUTH_METHOD', Config::get('services.runcloud.ssl_authorization_method', 'http-01'));
        $externalApiId = $this->client->getEnvValue(
            'RUNCLOUD_CLOUDFLARE_EXTERNAL_API_ID',
            Config::get('services.runcloud.cloudflare_external_api_id'),
        );

        // 3. Deploy Let's Encrypt to the domain under Advanced SSL
        $payload = [
            'provider' => 'letsencrypt',
            'authorizationMethod' => $authMethod,
            'environment' => 'live',
            'enableHttp' => ($authMethod === 'http-01'),
            'enableHsts' => false,
            'rsa_key_size' => 2048,
        ];

        if ($authMethod === 'dns-01') {
            if (! is_numeric($externalApiId) || (int) $externalApiId <= 0) {
                throw new Exception('RUNCLOUD_CLOUDFLARE_EXTERNAL_API_ID must contain a valid RunCloud Cloudflare integration ID when DNS-01 SSL is enabled.');
            }

            $payload['externalApi'] = (int) $externalApiId;
        }

        $response = $this->client->client()->post("/servers/{$serverId}/webapps/{$appId}/domains/{$domainId}/ssl", $payload);

        // If certificate already exists or endpoint requires update, fall back to PUT /domains/{domainId}/ssl
        if ($response->status() === 404 || $response->status() === 405) {
            $response = $this->client->client()->put("/servers/{$serverId}/webapps/{$appId}/domains/{$domainId}/ssl", $payload);
        }

        if (! $response->successful()) {
            Log::error('RunCloud rejected the Advanced SSL certificate deployment', [
                'server_id' => $serverId,
                'app_id' => $appId,
                'domain_id' => $domainId,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            $this->client->handleApiError('RunCloud Advanced SSL certificate deployment failed', $response);
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
            $response = $this->client->client()->post("/servers/{$serverId}/webapps/{$appId}/ssl/advanced", [
                'advancedSSL' => true,
                'autoSSL' => true,
            ]);

            if (! $response->successful()) {
                Log::error('RunCloud rejected Advanced SSL with AutoSSL', [
                    'server_id' => $serverId,
                    'app_id' => $appId,
                    'auto_ssl' => true,
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);
            }

            return $response->successful();
        } catch (Exception $e) {
            Log::warning('Failed to enable Advanced SSL mode on webapp: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Request SSL for a web application.
     * POST /ssl = install (new webapp, no cert yet).
     * If already installed (404/405), falls back to PUT /ssl/basic (update existing).
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function requestSsl(int $serverId, int $appId, ?array $customPayload = null): array
    {
        $payload = $customPayload ?? [
            'provider' => 'letsencrypt',
            'enableHttp' => true,
            'enableHsts' => false,
            'authorizationMethod' => 'http-01',
        ];

        // POST /ssl = install (new webapp, no cert yet)
        $response = $this->client->client()->post("/servers/{$serverId}/webapps/{$appId}/ssl", $payload);

        // If already installed, fall back to PUT /ssl/basic (update existing)
        if ($response->status() === 404 || $response->status() === 405) {
            $response = $this->client->client()->put("/servers/{$serverId}/webapps/{$appId}/ssl/basic", $payload);
        }

        if (! $response->successful()) {
            $this->client->handleApiError('RunCloud request SSL failed', $response);
        }

        return $response->json('data') ?? $response->json() ?? [];
    }

    /**
     * Get SSL configuration/status for a specific domain under Advanced SSL mode.
     *
     * @return array<string, mixed>
     */
    public function getDomainSsl(int $serverId, int $appId, int $domainId): array
    {
        $response = $this->client->client()->get("/servers/{$serverId}/webapps/{$appId}/domains/{$domainId}/ssl");

        if (! $response->successful()) {
            return [];
        }

        return $response->json('data') ?? $response->json() ?? [];
    }

    /**
     * Check if an active/valid certificate is already deployed for an app or domain.
     * Returns the certificate data if active, or null if no valid certificate exists.
     *
     * @return array<string, mixed>|null
     */
    public function getActiveSsl(int $serverId, int $appId, ?int $domainId = null): ?array
    {
        $data = null;

        // 1. Check domain-level SSL endpoint if domainId provided
        if ($domainId) {
            try {
                $data = $this->getDomainSsl($serverId, $appId, $domainId);
            } catch (Exception) {
                $data = null;
            }
        }

        // 2. Fall back to app-level SSL endpoint
        if (empty($data)) {
            try {
                $response = $this->client->client()->get("/servers/{$serverId}/webapps/{$appId}/ssl");
                if ($response->successful()) {
                    $data = $response->json('data') ?? $response->json() ?? [];
                }
            } catch (Exception) {
                $data = null;
            }
        }

        if (! empty($data)) {
            $status = strtolower((string) ($data['status'] ?? $data['state'] ?? ''));
            if (in_array($status, ['success', 'deployed', 'active', 'installed', 'valid'])) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Get SSL configuration/status for a web application.
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function getSsl(int $serverId, int $appId): array
    {
        $response = $this->client->client()->get("/servers/{$serverId}/webapps/{$appId}/ssl");

        if (! $response->successful()) {
            $this->client->handleApiError('RunCloud get SSL failed', $response);
        }

        return $response->json('data') ?? $response->json() ?? [];
    }

    /**
     * Check first if an active cert exists; if not, request SSL.
     *
     * @return array<string, mixed>
     */
    public function ensureSsl(int $serverId, int $appId, ?array $customPayload = null): array
    {
        $active = $this->getActiveSsl($serverId, $appId);
        if ($active !== null) {
            Log::info("Reusing existing active SSL certificate for app {$appId}");
            return $active;
        }

        return $this->requestSsl($serverId, $appId, $customPayload);
    }

    /**
     * Configure standard Let's Encrypt SSL via HTTP-01 challenge (fallback).
     *
     * @throws Exception
     */
    public function configureAutoSsl(int $serverId, int $appId): array
    {
        $authMethod = (string) $this->client->getEnvValue('RUNCLOUD_SSL_AUTH_METHOD', Config::get('services.runcloud.ssl_authorization_method', 'http-01'));
        $externalApiId = $this->client->getEnvValue(
            'RUNCLOUD_CLOUDFLARE_EXTERNAL_API_ID',
            Config::get('services.runcloud.cloudflare_external_api_id'),
        );

        $payload = [
            'provider' => 'letsencrypt',
            'authorizationMethod' => $authMethod,
            'environment' => 'live',
            'enableHttp' => false,
            'enableHsts' => false,
            'ssl_protocol_id' => 1,
            'rsa_key_size' => 2048,
        ];

        if ($authMethod === 'dns-01') {
            if (! is_numeric($externalApiId) || (int) $externalApiId <= 0) {
                throw new Exception('RUNCLOUD_CLOUDFLARE_EXTERNAL_API_ID must contain a valid RunCloud Cloudflare integration ID when DNS-01 SSL is enabled.');
            }

            $payload['externalApi'] = (int) $externalApiId;
        }

        $response = $this->client->client()->post("/servers/{$serverId}/webapps/{$appId}/ssl", $payload);

        // If already installed, fall back to PUT /ssl/basic (update existing)
        if ($response->status() === 404 || $response->status() === 405) {
            $response = $this->client->client()->put("/servers/{$serverId}/webapps/{$appId}/ssl/basic", $payload);
        }

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
     * Poll public DNS until the domain resolves to the target server IP.
     */
    public function waitForDnsPropagation(string $domain, string $expectedIp, int $maxAttempts = 10, int $sleepSeconds = 3): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        Log::info("Waiting for DNS propagation of {$domain} to {$expectedIp}...");

        for ($i = 1; $i <= $maxAttempts; $i++) {
            $records = @dns_get_record($domain, DNS_A);
            if (! empty($records)) {
                foreach ($records as $record) {
                    if (($record['ip'] ?? '') === $expectedIp) {
                        Log::info("DNS propagated successfully for {$domain} -> {$expectedIp} on attempt {$i}");
                        return true;
                    }
                }
            }

            if ($i < $maxAttempts) {
                sleep($sleepSeconds);
            }
        }

        Log::warning("DNS propagation check timed out after {$maxAttempts} attempts for {$domain}, proceeding anyway.");
        return false;
    }

    /**
     * Poll RunCloud until the SSL certificate status is active/installed.
     *
     * @param  int  $serverId
     * @param  int  $appId
     * @param  string  $domain
     * @param  int|null  $domainId
     * @param  int  $maxAttempts
     * @param  int  $sleepSeconds
     * @return bool
     */
    public function waitForSslIssued(int $serverId, int $appId, string $domain, ?int $domainId = null, int $maxAttempts = 30, int $sleepSeconds = 5): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        Log::info("Waiting for RunCloud SSL certificate issuance for app {$appId} ({$domain})...");

        for ($i = 1; $i <= $maxAttempts; $i++) {
            try {
                // 1. Try domain-level SSL endpoint first if domainId is known (Advanced SSL mode)
                $data = null;
                if ($domainId) {
                    $domainResponse = $this->client->client()->get("/servers/{$serverId}/webapps/{$appId}/domains/{$domainId}/ssl");
                    if ($domainResponse->successful()) {
                        $data = $domainResponse->json('data') ?? $domainResponse->json() ?? [];
                    }
                }

                // 2. Fall back to app-level SSL endpoint
                if (empty($data)) {
                    $response = $this->client->client()->get("/servers/{$serverId}/webapps/{$appId}/ssl");
                    if ($response->successful()) {
                        $data = $response->json('data') ?? $response->json() ?? [];
                    }
                }

                if (! empty($data)) {
                    $status = strtolower((string) ($data['status'] ?? $data['state'] ?? ''));

                    if (in_array($status, ['success', 'deployed', 'active', 'installed', 'valid'])) {
                        Log::info("RunCloud SSL successfully issued for {$domain} (status: {$status}) on attempt {$i}");
                        return true;
                    }

                    if (in_array($status, ['failed', 'error'])) {
                        Log::warning("RunCloud SSL reported failure for {$domain} on attempt {$i}", ['data' => $data]);
                        return false;
                    }
                }
            } catch (Exception $e) {
                Log::debug("Error polling RunCloud SSL status: {$e->getMessage()}");
            }

            if ($i < $maxAttempts) {
                sleep($sleepSeconds);
            }
        }

        Log::warning("RunCloud SSL issuance polling timed out after {$maxAttempts} attempts for {$domain}.");
        return false;
    }
}
