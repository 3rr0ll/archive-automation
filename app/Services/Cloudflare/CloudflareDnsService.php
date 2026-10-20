<?php

namespace App\Services\Cloudflare;

use App\Services\Cache\ApplicationCache;
use Exception;
use Illuminate\Support\Facades\Log;

class CloudflareDnsService
{
    public function __construct(
        protected CloudflareClient $client,
        private ApplicationCache $cache,
    ) {}

    /**
     * Reset DNS and set up RunCloud server DNS on Cloudflare:
     * 1. Delete all existing DNS records for the domain
     * 2. Add RunCloud server DNS for @ and www as A records (proxied = true)
     *
     * @param  string  $domain  Domain name
     * @param  string  $serverIp  RunCloud server IP address
     * @param  bool  $proxied  Cloudflare proxy status (default: true for Workers)
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function setupRunCloudDns(string $domain, string $serverIp, bool $proxied = true): array
    {
        $normalized = $this->client->normalizeDomain($domain);
        $rootDomain = $this->client->getRootDomain($normalized);
        $zoneId = $this->client->getZoneId($rootDomain);

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
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getDnsRecords(string $domain): array
    {
        $rootDomain = $this->client->getRootDomain($domain);
        $zoneId = $this->client->getZoneId($rootDomain);

        return $this->cache->rememberCloudflareDnsRecords(
            $zoneId,
            fn (): array => $this->fetchDnsRecords($zoneId, $rootDomain),
        );
    }

    /**
     * Delete all DNS records in the domain's Cloudflare zone.
     * Safely preserves zone apex NS records which Cloudflare manages and forbids deleting.
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function deleteAllDnsRecords(string $domain): array
    {
        $rootDomain = $this->client->getRootDomain($domain);
        $zoneId = $this->client->getZoneId($rootDomain);
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

            $response = $this->client->client()->delete("/zones/{$zoneId}/dns_records/{$id}");

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

        $this->cache->forgetCloudflareDnsRecords($zoneId);

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
     * @param  string  $type  Record type (A, CNAME, etc.)
     * @param  string  $name  Name (@, www, subdomain)
     * @param  string  $content  Value / IP address
     * @param  bool  $proxied  Proxied through Cloudflare CDN/Workers
     * @param  int  $ttl  TTL in seconds (1 for auto)
     * @return array<string, mixed>
     *
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
        $rootDomain = $this->client->getRootDomain($domain);
        $zoneId = $this->client->getZoneId($rootDomain);

        $payload = [
            'type' => strtoupper($type),
            'name' => $name,
            'content' => $content,
            'ttl' => $ttl,
            'proxied' => $proxied,
        ];

        $response = $this->client->client()->post("/zones/{$zoneId}/dns_records", $payload);

        if (! $response->successful()) {
            $this->client->handleApiError("Failed to create Cloudflare DNS record ({$type} {$name}) for {$rootDomain}", $response);
        }

        $this->cache->forgetCloudflareDnsRecords($zoneId);

        return $response->json('result') ?? [];
    }

    /**
     * List DNS records by zone ID.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function listDnsRecords(string $zoneId): array
    {
        return $this->cache->rememberCloudflareDnsRecords(
            $zoneId,
            fn (): array => $this->fetchDnsRecords($zoneId),
        );
    }

    /**
     * Add an A record by zone ID.
     *
     * @return array<string, mixed>
     *
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

        $response = $this->client->client()->post("/zones/{$zoneId}/dns_records", $payload);

        if (! $response->successful()) {
            $this->client->handleApiError("Failed to add A record [{$name}] for zone {$zoneId}", $response);
        }

        $this->cache->forgetCloudflareDnsRecords($zoneId);

        return $response->json('result', []);
    }

    /**
     * Upsert an A record: updates matching record and removes duplicates, or creates if absent.
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function upsertARecord(string $zoneId, string $name, string $ip, bool $proxied = true): array
    {
        $existingResponse = $this->client->client()->get("/zones/{$zoneId}/dns_records", [
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

            $response = $this->client->client()->put("/zones/{$zoneId}/dns_records/{$first['id']}", [
                'type' => 'A',
                'name' => $name,
                'content' => $ip,
                'proxied' => $proxied,
                'ttl' => 1,
            ]);

            if (! $response->successful()) {
                $this->client->handleApiError("Failed to update A record [{$name}] for zone {$zoneId}", $response);
            }

            $this->cache->forgetCloudflareDnsRecords($zoneId);

            return $response->json('result', []);
        }

        return $this->addARecord($zoneId, $name, $ip, $proxied);
    }

    /**
     * Update the Cloudflare proxy status (proxied boolean) for A records (@ and www) of a domain.
     *
     * @param  string  $domain  Domain name
     * @param  bool  $proxied  Whether to proxy through Cloudflare
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function setProxyStatus(string $domain, bool $proxied = true): array
    {
        $rootDomain = $this->client->getRootDomain($domain);
        $zoneId = $this->client->getZoneId($rootDomain);

        $response = $this->client->client()->get("/zones/{$zoneId}/dns_records", [
            'type' => 'A',
            'per_page' => 100,
        ]);

        $records = $response->successful() ? ($response->json('result', [])) : [];
        $targetNames = [
            strtolower($rootDomain),
            'www.'.strtolower($rootDomain),
            '@',
            'www',
        ];

        $updated = [];
        foreach ($records as $record) {
            $recordName = strtolower(trim((string) ($record['name'] ?? '')));
            if (in_array($recordName, $targetNames, true)) {
                $recordId = (string) $record['id'];
                $putResponse = $this->client->client()->put("/zones/{$zoneId}/dns_records/{$recordId}", [
                    'type' => 'A',
                    'name' => $record['name'],
                    'content' => $record['content'],
                    'proxied' => $proxied,
                    'ttl' => 1,
                ]);

                if ($putResponse->successful()) {
                    $updated[] = $putResponse->json('result', []);
                } else {
                    Log::warning("Failed to update proxy status for [{$recordName}] on zone {$zoneId}", [
                        'error' => $putResponse->body(),
                    ]);
                }
            }
        }

        $this->cache->forgetCloudflareDnsRecords($zoneId);

        return [
            'domain' => $rootDomain,
            'zone_id' => $zoneId,
            'proxied' => $proxied,
            'updated_records' => $updated,
        ];
    }

    /**
     * Delete a single DNS record by zone ID and record ID.
     *
     * @throws Exception
     */
    public function deleteDnsRecord(string $zoneId, string $recordId): void
    {
        $response = $this->client->client()->delete("/zones/{$zoneId}/dns_records/{$recordId}");

        if (! $response->successful()) {
            $this->client->handleApiError("Failed to delete DNS record [{$recordId}] for zone {$zoneId}", $response);
        }

        $this->cache->forgetCloudflareDnsRecords($zoneId);
    }

    /**
     * Apply DNS changes or worker configurations.
     *
     * @param  array<int, array<string, mixed>>  $records
     *
     * @throws Exception
     */
    public function configureDns(string $domain, array $records): bool
    {
        $rootDomain = $this->client->getRootDomain($domain);

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
     * @return array<int, array<string, mixed>>
     */
    private function fetchDnsRecords(string $zoneId, ?string $domain = null): array
    {
        $page = 1;
        $allRecords = [];

        do {
            $response = $this->client->client()->get("/zones/{$zoneId}/dns_records", [
                'page' => $page,
                'per_page' => 100,
            ]);

            if (! $response->successful()) {
                $context = $domain === null
                    ? "Failed to list DNS records for zone {$zoneId}"
                    : "Failed to fetch Cloudflare DNS records for {$domain}";
                $this->client->handleApiError($context, $response);
            }

            $records = $response->json('result') ?? [];
            $allRecords = array_merge($allRecords, $records);
            $totalPages = (int) ($response->json('result_info.total_pages') ?? 1);
            $page++;
        } while ($page <= $totalPages && ! empty($records));

        return $allRecords;
    }
}
