<?php

namespace App\Services\Cloudflare;

use App\Models\EnvironmentVariable;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflareClient
{
    protected string $baseUrl;
    protected ?string $apiToken;
    protected ?string $apiKey;
    protected ?string $email;
    protected ?string $accountId;
    protected int $timeout;

    public function __construct(
        ?string $apiToken = null,
        ?string $apiKey = null,
        ?string $email = null
    ) {
        $this->baseUrl = rtrim((string) Config::get('services.cloudflare.base_url', 'https://api.cloudflare.com/client/v4'), '/');
        $this->apiToken = $apiToken ?? Config::get('services.cloudflare.api_token') ?? Config::get('services.cloudflare.token');
        $this->apiKey = $apiKey ?? Config::get('services.cloudflare.api_key');
        $this->email = $email ?? Config::get('services.cloudflare.email');
        $this->accountId = Config::get('services.cloudflare.account_id');
        $this->timeout = (int) Config::get('services.cloudflare.timeout', 30);
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
     * Prepare authenticated HTTP client.
     *
     * @return PendingRequest
     * @throws Exception
     */
    public function client(): PendingRequest
    {
        // Prioritize database EnvironmentVariables as primary source of truth
        $token = $this->getEnvValue('CLOUDFLARE_API_TOKEN', $this->apiToken);
        $apiKey = $this->getEnvValue('CLOUDFLARE_API_KEY', $this->apiKey);
        $email = $this->getEnvValue('CLOUDFLARE_EMAIL', $this->email);

        $client = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);

        if (! empty($token)) {
            return $client->withToken($token);
        }

        if (! empty($apiKey) && ! empty($email)) {
            return $client->withHeaders([
                'X-Auth-Key' => $apiKey,
                'X-Auth-Email' => $email,
            ]);
        }

        throw new Exception('Cloudflare credentials not configured. Please supply CLOUDFLARE_API_TOKEN in your Environment Variables UI or .env file.');
    }

    /**
     * Find zone by walking domain labels from most-specific to least-specific.
     * Handles ccTLD second-level domains (e.g. rocketcasinos.it.com -> tries rocketcasinos.it.com, then it.com).
     *
     * @param string $domain
     * @return array<string, mixed>|null
     */
    public function findZoneByDomain(string $domain): ?array
    {
        $normalized = $this->normalizeDomain($domain);
        $parts = explode('.', $normalized);
        $total = count($parts);

        for ($i = 0; $i <= $total - 2; $i++) {
            $candidate = implode('.', array_slice($parts, $i));
            try {
                $response = $this->client()->get('/zones', ['name' => $candidate, 'per_page' => 1]);
                if ($response->successful()) {
                    $results = $response->json('result', []);
                    if (! empty($results[0]['id'])) {
                        return $results[0];
                    }
                }
            } catch (\Throwable) {
                // Try next candidate
            }
        }

        return null;
    }

    /**
     * Resolve Cloudflare Zone ID for a domain.
     *
     * @param string $domain
     * @return string
     * @throws Exception
     */
    public function getZoneId(string $domain): string
    {
        $normalized = $this->normalizeDomain($domain);
        $rootDomain = $this->getRootDomain($normalized);

        // 1. Try querying with the root domain
        $response = $this->client()->get('/zones', ['name' => $rootDomain]);
        if ($response->successful()) {
            $zones = $response->json('result') ?? [];
            if (! empty($zones[0]['id'])) {
                return (string) $zones[0]['id'];
            }
        }

        // 2. If different, try querying with the normalized domain
        if ($normalized !== $rootDomain) {
            $response = $this->client()->get('/zones', ['name' => $normalized]);
            if ($response->successful()) {
                $zones = $response->json('result') ?? [];
                if (! empty($zones[0]['id'])) {
                    return (string) $zones[0]['id'];
                }
            }
        }

        // 3. Multi-label fallback for ccTLDs
        $zone = $this->findZoneByDomain($normalized);
        if (! empty($zone['id'])) {
            return (string) $zone['id'];
        }

        throw new Exception("Cloudflare zone not found for domain [{$domain}].");
    }

    /**
     * Create a zone on Cloudflare.
     *
     * @param string $domain
     * @return array<string, mixed>
     * @throws Exception
     */
    public function createZone(string $domain): array
    {
        $response = $this->client()->post('/zones', [
            'name' => $this->normalizeDomain($domain),
            'jump_start' => false,
        ]);

        if (! $response->successful()) {
            $this->handleApiError("Failed to create zone for {$domain}", $response);
        }

        return $response->json('result', []);
    }

    /**
     * Get details of a zone by zone ID.
     *
     * @param string $zoneId
     * @return array<string, mixed>
     * @throws Exception
     */
    public function getZone(string $zoneId): array
    {
        $response = $this->client()->get("/zones/{$zoneId}");

        if (! $response->successful()) {
            $this->handleApiError("Failed to get zone {$zoneId}", $response);
        }

        return $response->json('result', []);
    }

    /**
     * Purge Cloudflare cache for a zone.
     *
     * @param string $zoneId
     * @param bool $purgeEverything
     * @return array<string, mixed>
     * @throws Exception
     */
    public function purgeCache(string $zoneId, bool $purgeEverything = true): array
    {
        $payload = $purgeEverything ? ['purge_everything' => true] : [];

        $response = $this->client()->post("/zones/{$zoneId}/purge_cache", $payload);

        if (! $response->successful()) {
            $this->handleApiError("Failed to purge cache for zone {$zoneId}", $response);
        }

        return $response->json('result', []);
    }

    /**
     * Normalize domain string: strips protocol, paths, ports, query strings, and whitespace.
     *
     * @param string $domain
     * @return string
     */
    public function normalizeDomain(string $domain): string
    {
        $clean = strtolower(trim($domain));
        $clean = preg_replace('#^https?://#', '', $clean) ?? $clean;
        $clean = explode('/', $clean)[0];
        $clean = explode(':', $clean)[0];
        $clean = explode('?', $clean)[0];

        return trim($clean);
    }

    /**
     * Get root domain (e.g. "www.example.com" -> "example.com").
     *
     * @param string $domain
     * @return string
     */
    public function getRootDomain(string $domain): string
    {
        $normalized = $this->normalizeDomain($domain);

        if (str_starts_with($normalized, 'www.')) {
            return substr($normalized, 4);
        }

        return $normalized;
    }

    /**
     * Handle and normalize API errors.
     *
     * @param string $context
     * @param Response $response
     * @return void
     * @throws Exception
     */
    public function handleApiError(string $context, Response $response): void
    {
        $statusCode = $response->status();
        $errorBody = $response->json();

        $errors = $errorBody['errors'] ?? [];
        $firstErrorMessage = $errors[0]['message'] ?? null;
        $message = $firstErrorMessage ?? $errorBody['message'] ?? $errorBody['error'] ?? $response->body();

        Log::error("{$context}: HTTP {$statusCode}", [
            'status' => $statusCode,
            'message' => $message,
        ]);

        throw new Exception("Cloudflare API error ({$statusCode}): {$message}");
    }
}
