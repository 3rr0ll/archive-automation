<?php

namespace App\Services\Cloudflare;

use App\Models\ApiCredential;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflareService
{
    // Worker script identifiers
    public const WORKER_WWW_MAIN = 'all-scripts-worker-www';
    public const WORKER_WWW_REDIRECT = 'domain-redirect-root-to-www';
    public const WORKER_ROOT_MAIN = 'all-scripts-worker-root';
    public const WORKER_ROOT_REDIRECT = 'domain-redirect-www-to-root';

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
     * Change Cloudflare Worker routes for a domain based on www / non-www version preference:
     * - If www version:
     *     www.domain.com/* -> all-scripts-worker-www
     *     domain.com/*     -> domain-redirect-root-to-www
     * - If non-www version:
     *     domain.com/*     -> all-scripts-worker-root
     *     www.domain.com/* -> domain-redirect-www-to-root
     *
     * @param string $domain The domain name
     * @param string|null $version 'www' or 'nonwww' (optional; inferred if omitted)
     * @return array<string, mixed>
     * @throws Exception
     */
    public function changeWorkers(string $domain, ?string $version = null): array
    {
        $normalized = $this->normalizeDomain($domain);
        $rootDomain = $this->getRootDomain($normalized);

        // Normalize version choice
        $versionClean = strtolower(trim((string) $version));
        if ($versionClean === '') {
            $versionClean = str_starts_with($normalized, 'www.') ? 'www' : 'nonwww';
        } elseif (in_array($versionClean, ['root', 'non-www', 'non_www'], true)) {
            $versionClean = 'nonwww';
        }

        if ($versionClean === 'www') {
            $targetRoutes = [
                [
                    'pattern' => "www.{$rootDomain}/*",
                    'script' => self::WORKER_WWW_MAIN,
                ],
                [
                    'pattern' => "{$rootDomain}/*",
                    'script' => self::WORKER_WWW_REDIRECT,
                ],
            ];
        } else {
            $targetRoutes = [
                [
                    'pattern' => "{$rootDomain}/*",
                    'script' => self::WORKER_ROOT_MAIN,
                ],
                [
                    'pattern' => "www.{$rootDomain}/*",
                    'script' => self::WORKER_ROOT_REDIRECT,
                ],
            ];
        }

        $zoneId = $this->getZoneId($rootDomain);
        $existingRoutes = $this->getWorkerRoutes($zoneId);

        $results = [];

        foreach ($targetRoutes as $target) {
            $pattern = $target['pattern'];
            $script = $target['script'];

            // Find all matching routes for this pattern
            $matches = array_values(array_filter(
                $existingRoutes,
                fn (array $route) => strtolower($route['pattern'] ?? '') === strtolower($pattern)
            ));

            if (! empty($matches)) {
                $primary = $matches[0];
                $primaryId = $primary['id'] ?? '';

                if (($primary['script'] ?? '') === $script) {
                    $results[] = [
                        'id' => $primaryId,
                        'pattern' => $pattern,
                        'script' => $script,
                        'action' => 'unchanged',
                    ];
                } else {
                    $updated = $this->updateWorkerRoute($zoneId, $primaryId, $pattern, $script);
                    $results[] = [
                        'id' => $updated['id'] ?? $primaryId,
                        'pattern' => $pattern,
                        'script' => $script,
                        'action' => 'updated',
                    ];
                }

                // Delete any duplicate routes matching the same pattern to keep configuration clean
                for ($i = 1; $i < count($matches); $i++) {
                    if (! empty($matches[$i]['id'])) {
                        $this->deleteWorkerRoute($zoneId, $matches[$i]['id']);
                    }
                }
            } else {
                $created = $this->createWorkerRoute($zoneId, $pattern, $script);
                $results[] = [
                    'id' => $created['id'] ?? null,
                    'pattern' => $pattern,
                    'script' => $script,
                    'action' => 'created',
                ];
            }
        }

        Log::info('Cloudflare worker routes updated successfully', [
            'domain' => $domain,
            'root_domain' => $rootDomain,
            'version' => $versionClean,
            'zone_id' => $zoneId,
            'routes' => $results,
        ]);

        return [
            'domain' => $domain,
            'root_domain' => $rootDomain,
            'version' => $versionClean,
            'zone_id' => $zoneId,
            'routes' => $results,
        ];
    }

    /**
     * Reset DNS and set up RunCloud server DNS on Cloudflare:
     * 1. Delete all existing DNS records for the domain
     * 2. Add RunCloud server DNS for @ and www as A records (proxied = true)
     *
     * @param string $domain Domain name
     * @param string $serverIp RunCloud server IP address
     * @param bool $proxied Cloudflare proxy status (default: true for Workers)
     * @return array<string, mixed>
     * @throws Exception
     */
    public function setupRunCloudDns(string $domain, string $serverIp, bool $proxied = true): array
    {
        $normalized = $this->normalizeDomain($domain);
        $rootDomain = $this->getRootDomain($normalized);
        $zoneId = $this->getZoneId($rootDomain);

        Log::info('Initiating Cloudflare DNS setup for RunCloud server', [
            'domain' => $rootDomain,
            'server_ip' => $serverIp,
            'zone_id' => $zoneId,
            'proxied' => $proxied,
        ]);

        // 1. Delete all DNS records
        $deleteResult = $this->deleteAllDnsRecords($rootDomain);

        // 2. Add A record for @
        $rootRecord = $this->addDnsRecord($rootDomain, 'A', '@', $serverIp, $proxied, 1);

        // 3. Add A record for www
        $wwwRecord = $this->addDnsRecord($rootDomain, 'A', 'www', $serverIp, $proxied, 1);

        Log::info('Cloudflare DNS configured for RunCloud server successfully', [
            'domain' => $rootDomain,
            'server_ip' => $serverIp,
            'deleted_count' => $deleteResult['deleted_count'],
            'root_record_id' => $rootRecord['id'] ?? null,
            'www_record_id' => $wwwRecord['id'] ?? null,
        ]);

        return [
            'domain' => $rootDomain,
            'server_ip' => $serverIp,
            'proxied' => $proxied,
            'zone_id' => $zoneId,
            'deleted_records_count' => $deleteResult['deleted_count'],
            'skipped_records_count' => $deleteResult['skipped_count'],
            'records' => [
                'root' => $rootRecord,
                'www' => $wwwRecord,
            ],
        ];
    }

    /**
     * Check existing DNS records for the given domain.
     *
     * @param string $domain
     * @return array<int, array<string, mixed>>
     * @throws Exception
     */
    public function getDnsRecords(string $domain): array
    {
        $rootDomain = $this->getRootDomain($domain);
        $zoneId = $this->getZoneId($rootDomain);

        $page = 1;
        $allRecords = [];

        do {
            $response = $this->client()->get("/zones/{$zoneId}/dns_records", [
                'page' => $page,
                'per_page' => 100,
            ]);

            if (! $response->successful()) {
                $this->handleApiError("Failed to fetch Cloudflare DNS records for {$rootDomain}", $response);
            }

            $records = $response->json('result') ?? [];
            $allRecords = array_merge($allRecords, $records);

            $resultInfo = $response->json('result_info') ?? [];
            $totalPages = (int) ($resultInfo['total_pages'] ?? 1);

            $page++;
        } while ($page <= $totalPages && ! empty($records));

        return $allRecords;
    }

    /**
     * Delete all DNS records in the domain's Cloudflare zone.
     * Safely preserves zone apex NS records which Cloudflare manages and forbids deleting.
     *
     * @param string $domain
     * @return array<string, mixed>
     * @throws Exception
     */
    public function deleteAllDnsRecords(string $domain): array
    {
        $rootDomain = $this->getRootDomain($domain);
        $zoneId = $this->getZoneId($rootDomain);
        $records = $this->getDnsRecords($rootDomain);

        $deletedCount = 0;
        $skippedCount = 0;
        $deletedRecords = [];

        foreach ($records as $record) {
            $id = $record['id'] ?? '';
            $type = strtoupper($record['type'] ?? '');
            $name = strtolower($record['name'] ?? '');

            // In Cloudflare, apex NS records cannot be deleted via the API
            if ($type === 'NS' && ($name === strtolower($rootDomain) || $name === '@')) {
                $skippedCount++;
                continue;
            }

            if (empty($id)) {
                continue;
            }

            $response = $this->client()->delete("/zones/{$zoneId}/dns_records/{$id}");

            if ($response->successful()) {
                $deletedCount++;
                $deletedRecords[] = [
                    'id' => $id,
                    'name' => $record['name'] ?? '',
                    'type' => $type,
                ];
            } else {
                Log::warning("Could not delete DNS record [{$id}] ({$type} {$name})", [
                    'error' => $response->body(),
                ]);
                $skippedCount++;
            }
        }

        return [
            'domain' => $rootDomain,
            'zone_id' => $zoneId,
            'deleted_count' => $deletedCount,
            'skipped_count' => $skippedCount,
            'deleted_records' => $deletedRecords,
        ];
    }

    /**
     * Add a DNS record to Cloudflare zone.
     *
     * @param string $domain
     * @param string $type Record type (A, CNAME, etc.)
     * @param string $name Name (@, www, subdomain)
     * @param string $content Value / IP address
     * @param bool $proxied Proxied through Cloudflare CDN/Workers
     * @param int $ttl TTL in seconds (1 for auto)
     * @return array<string, mixed>
     * @throws Exception
     */
    public function addDnsRecord(
        string $domain,
        string $type,
        string $name,
        string $content,
        bool $proxied = true,
        int $ttl = 1
    ): array {
        $rootDomain = $this->getRootDomain($domain);
        $zoneId = $this->getZoneId($rootDomain);

        $payload = [
            'type' => strtoupper($type),
            'name' => $name,
            'content' => $content,
            'ttl' => $ttl,
            'proxied' => $proxied,
        ];

        $response = $this->client()->post("/zones/{$zoneId}/dns_records", $payload);

        if (! $response->successful()) {
            $this->handleApiError("Failed to create Cloudflare DNS record ({$type} {$name}) for {$rootDomain}", $response);
        }

        return $response->json('result') ?? [];
    }

    /**
     * Apply DNS changes or worker configurations.
     *
     * @param string $domain
     * @param array<int, array<string, mixed>> $records
     * @return bool
     * @throws Exception
     */
    public function configureDns(string $domain, array $records): bool
    {
        $rootDomain = $this->getRootDomain($domain);

        foreach ($records as $record) {
            $type = $record['type'] ?? 'A';
            $name = $record['name'] ?? '@';
            $content = $record['content'] ?? '';
            $proxied = (bool) ($record['proxied'] ?? true);
            $ttl = (int) ($record['ttl'] ?? 1);

            $this->addDnsRecord($rootDomain, $type, $name, $content, $proxied, $ttl);
        }

        return true;
    }

    /**
     * Get Cloudflare Zone ID for a domain.
     *
     * @param string $domain
     * @return string
     * @throws Exception
    /**
     * Find zone by domain walking from most-specific to least-specific labels (min 2 labels).
     * e.g. "sub.example.co.uk" tries "sub.example.co.uk", then "example.co.uk", then "co.uk".
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
     * Get Cloudflare Zone ID for a domain.
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

        // 3. Multi-label fallback for ccTLDs (e.g. rocketcasinos.it.com)
        $parts = explode('.', $normalized);
        $total = count($parts);
        for ($i = 0; $i <= $total - 2; $i++) {
            $candidate = implode('.', array_slice($parts, $i));
            if ($candidate === $rootDomain || $candidate === $normalized) {
                continue;
            }
            try {
                $response = $this->client()->get('/zones', ['name' => $candidate]);
                if ($response->successful()) {
                    $zones = $response->json('result', []);
                    if (! empty($zones[0]['id'])) {
                        return (string) $zones[0]['id'];
                    }
                }
            } catch (\Throwable) {
                // Try next
            }
        }

        throw new Exception("Cloudflare zone not found for domain [{$domain}].");
    }

    /**
     * Create a new zone on Cloudflare.
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
     * Get zone details by zone ID.
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
     * List DNS records by zone ID.
     *
     * @param string $zoneId
     * @return array<int, array<string, mixed>>
     * @throws Exception
     */
    public function listDnsRecords(string $zoneId): array
    {
        $response = $this->client()->get("/zones/{$zoneId}/dns_records", [
            'per_page' => 100,
        ]);

        if (! $response->successful()) {
            $this->handleApiError("Failed to list DNS records for zone {$zoneId}", $response);
        }

        return $response->json('result', []);
    }

    /**
     * Add an A record by zone ID.
     *
     * @param string $zoneId
     * @param string $name
     * @param string $ip
     * @param bool $proxied
     * @return array<string, mixed>
     * @throws Exception
     */
    public function addARecord(string $zoneId, string $name, string $ip, bool $proxied = true): array
    {
        $payload = [
            'type' => 'A',
            'name' => $name,
            'content' => $ip,
            'proxied' => $proxied,
            'ttl' => 1,
        ];

        $response = $this->client()->post("/zones/{$zoneId}/dns_records", $payload);

        if (! $response->successful()) {
            $this->handleApiError("Failed to add A record [{$name}] for zone {$zoneId}", $response);
        }

        return $response->json('result', []);
    }

    /**
     * Upsert an A record: updates matching record and removes duplicates, or creates if absent.
     *
     * @param string $zoneId
     * @param string $name
     * @param string $ip
     * @param bool $proxied
     * @return array<string, mixed>
     * @throws Exception
     */
    public function upsertARecord(string $zoneId, string $name, string $ip, bool $proxied = true): array
    {
        $existingResponse = $this->client()->get("/zones/{$zoneId}/dns_records", [
            'type' => 'A',
            'name' => $name,
            'per_page' => 100,
        ]);

        $records = $existingResponse->successful() ? ($existingResponse->json('result', [])) : [];

        if (! empty($records)) {
            $first = array_shift($records);

            foreach ($records as $dupe) {
                try {
                    $this->deleteDnsRecord($zoneId, (string) $dupe['id']);
                } catch (\Throwable) {
                    // Ignore duplicate deletion error
                }
            }

            $response = $this->client()->put("/zones/{$zoneId}/dns_records/{$first['id']}", [
                'type' => 'A',
                'name' => $name,
                'content' => $ip,
                'proxied' => $proxied,
                'ttl' => 1,
            ]);

            if (! $response->successful()) {
                $this->handleApiError("Failed to update A record [{$name}] for zone {$zoneId}", $response);
            }

            return $response->json('result', []);
        }

        return $this->addARecord($zoneId, $name, $ip, $proxied);
    }

    /**
     * Delete a single DNS record by zone ID and record ID.
     *
     * @param string $zoneId
     * @param string $recordId
     * @return void
     * @throws Exception
     */
    public function deleteDnsRecord(string $zoneId, string $recordId): void
    {
        $response = $this->client()->delete("/zones/{$zoneId}/dns_records/{$recordId}");

        if (! $response->successful()) {
            $this->handleApiError("Failed to delete DNS record [{$recordId}] for zone {$zoneId}", $response);
        }
    }

    /**
     * Get all worker routes configured for a zone.
     *
     * @param string $zoneId
     * @return array<int, array<string, mixed>>
     * @throws Exception
     */
    public function getWorkerRoutes(string $zoneId): array
    {
        $response = $this->client()->get("/zones/{$zoneId}/workers/routes");

        if (! $response->successful()) {
            $this->handleApiError("Failed to fetch worker routes for zone {$zoneId}", $response);
        }

        return $response->json('result') ?? [];
    }

    /**
     * Create a new worker route in a zone.
     *
     * @param string $zoneId
     * @param string $pattern Route pattern (e.g. "www.example.com/*")
     * @param string $script Worker script name
     * @return array<string, mixed>
     * @throws Exception
     */
    public function createWorkerRoute(string $zoneId, string $pattern, string $script): array
    {
        $payload = [
            'pattern' => $pattern,
            'script' => $script,
        ];

        $response = $this->client()->post("/zones/{$zoneId}/workers/routes", $payload);

        if (! $response->successful()) {
            $this->handleApiError("Failed to create worker route [{$pattern}] for script [{$script}]", $response);
        }

        return $response->json('result') ?? [];
    }

    /**
     * Update an existing worker route in a zone.
     *
     * @param string $zoneId
     * @param string $routeId Route ID
     * @param string $pattern Route pattern
     * @param string $script Worker script name
     * @return array<string, mixed>
     * @throws Exception
     */
    public function updateWorkerRoute(string $zoneId, string $routeId, string $pattern, string $script): array
    {
        $payload = [
            'pattern' => $pattern,
            'script' => $script,
        ];

        $response = $this->client()->put("/zones/{$zoneId}/workers/routes/{$routeId}", $payload);

        if (! $response->successful()) {
            $this->handleApiError("Failed to update worker route [{$routeId}] to script [{$script}]", $response);
        }

        return $response->json('result') ?? [];
    }

    /**
     * Delete an existing worker route in a zone.
     *
     * @param string $zoneId
     * @param string $routeId
     * @return bool
     */
    public function deleteWorkerRoute(string $zoneId, string $routeId): bool
    {
        try {
            $response = $this->client()->delete("/zones/{$zoneId}/workers/routes/{$routeId}");
            return $response->successful();
        } catch (Exception $e) {
            Log::warning("Failed to delete worker route {$routeId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Normalize domain string: strips protocol, paths, ports, whitespace, lowercases.
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
     * Prepare HTTP client with Bearer Token or legacy API Key authentication.
     *
     * @return PendingRequest
     * @throws Exception
     */
    protected function client(): PendingRequest
    {
        $token = $this->apiToken;
        $apiKey = $this->apiKey;
        $email = $this->email;

        // If credentials are not set in config, attempt to retrieve from stored ApiCredential
        if (empty($token) && (empty($apiKey) || empty($email))) {
            try {
                $credential = ApiCredential::where('service', 'cloudflare')->latest()->first();
                if ($credential && ! empty($credential->tokenData())) {
                    $token = $credential->tokenData();
                }
            } catch (Exception) {
                // Table might not exist or database unavailable during certain tests
            }
        }

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

        throw new Exception('Cloudflare credentials not configured. Please supply CLOUDFLARE_API_TOKEN in your .env or save Cloudflare API credentials.');
    }

    /**
     * Handle and normalize API errors.
     *
     * @param string $context
     * @param Response $response
     * @return void
     * @throws Exception
     */
    protected function handleApiError(string $context, Response $response): void
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
