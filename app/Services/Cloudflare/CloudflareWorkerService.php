<?php

namespace App\Services\Cloudflare;

use App\Models\EnvironmentVariable;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class CloudflareWorkerService
{
    // Default Worker script names
    public const WORKER_WWW_MAIN = 'all-scripts-worker-www';
    public const WORKER_WWW_REDIRECT = 'domain-redirect-root-to-www';
    public const WORKER_ROOT_MAIN = 'all-scripts-worker-root';
    public const WORKER_ROOT_REDIRECT = 'domain-redirect-www-to-root';

    public function __construct(
        protected CloudflareClient $client
    ) {}

    /**
     * Retrieve a setting from EnvironmentVariable model first, then env().
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

        $envVal = env($key);
        if ($envVal !== null && $envVal !== '') {
            return $envVal;
        }

        return $default;
    }

    /**
     * Get the worker script name for www main route.
     *
     * @throws Exception
     */
    public function getWorkerWwwMain(): string
    {
        $val = $this->getEnvValue('CLOUDFLARE_WORKER_WWW_MAIN', self::WORKER_WWW_MAIN);
        if (empty($val)) {
            throw new Exception('Cloudflare worker script for WWW main route is not configured. Please supply CLOUDFLARE_WORKER_WWW_MAIN in Environment Variables.');
        }

        return (string) $val;
    }

    /**
     * Get the worker script name for root-to-www redirect route.
     *
     * @throws Exception
     */
    public function getWorkerWwwRedirect(): string
    {
        $val = $this->getEnvValue('CLOUDFLARE_WORKER_WWW_REDIRECT', self::WORKER_WWW_REDIRECT);
        if (empty($val)) {
            throw new Exception('Cloudflare worker script for WWW redirect route is not configured. Please supply CLOUDFLARE_WORKER_WWW_REDIRECT in Environment Variables.');
        }

        return (string) $val;
    }

    /**
     * Get the worker script name for non-www / root main route.
     *
     * @throws Exception
     */
    public function getWorkerRootMain(): string
    {
        $val = $this->getEnvValue('CLOUDFLARE_WORKER_ROOT_MAIN', self::WORKER_ROOT_MAIN);
        if (empty($val)) {
            throw new Exception('Cloudflare worker script for Root main route is not configured. Please supply CLOUDFLARE_WORKER_ROOT_MAIN in Environment Variables.');
        }

        return (string) $val;
    }

    /**
     * Get the worker script name for www-to-root redirect route.
     *
     * @throws Exception
     */
    public function getWorkerRootRedirect(): string
    {
        $val = $this->getEnvValue('CLOUDFLARE_WORKER_ROOT_REDIRECT', self::WORKER_ROOT_REDIRECT);
        if (empty($val)) {
            throw new Exception('Cloudflare worker script for Root redirect route is not configured. Please supply CLOUDFLARE_WORKER_ROOT_REDIRECT in Environment Variables.');
        }

        return (string) $val;
    }

    /**
     * Change Cloudflare Worker routes for a domain:
     * - www version:
     *     www.domain.com/* -> all-scripts-worker-www (or configured worker)
     *     domain.com/*     -> domain-redirect-root-to-www (or configured worker)
     * - nonwww version:
     *     domain.com/*     -> all-scripts-worker-root (or configured worker)
     *     www.domain.com/* -> domain-redirect-www-to-root (or configured worker)
     *
     * @param string $domain The domain name
     * @param string|null $version 'www' or 'nonwww' (optional; inferred if omitted)
     * @return array<string, mixed>
     * @throws Exception
     */
    public function changeWorkers(string $domain, ?string $version = null): array
    {
        $normalized = $this->client->normalizeDomain($domain);
        $rootDomain = $this->client->getRootDomain($normalized);

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
                    'script' => $this->getWorkerWwwMain(),
                ],
                [
                    'pattern' => "{$rootDomain}/*",
                    'script' => $this->getWorkerWwwRedirect(),
                ],
            ];
        } else {
            $targetRoutes = [
                [
                    'pattern' => "{$rootDomain}/*",
                    'script' => $this->getWorkerRootMain(),
                ],
                [
                    'pattern' => "www.{$rootDomain}/*",
                    'script' => $this->getWorkerRootRedirect(),
                ],
            ];
        }

        $zoneId = $this->client->getZoneId($rootDomain);
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

                // Delete any duplicate routes matching the same pattern
                for ($i = 1; $i < count($matches); $i++) {
                    if (! empty($matches[$i]['id'])) {
                        $this->deleteWorkerRoute($zoneId, (string) $matches[$i]['id']);
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

        // Purge cache after assigning worker routes so changes take effect immediately
        $cachePurged = false;
        try {
            $this->client->purgeCache($zoneId, true);
            $cachePurged = true;
            Log::info("Cloudflare cache purged successfully for zone {$zoneId} after assigning worker routes.");
        } catch (Exception $e) {
            Log::warning("Failed to purge cache for zone {$zoneId} after assigning worker routes: " . $e->getMessage());
        }

        Log::info('Cloudflare worker routes updated successfully', [
            'domain' => $domain,
            'root_domain' => $rootDomain,
            'version' => $versionClean,
            'zone_id' => $zoneId,
            'routes' => $results,
            'cache_purged' => $cachePurged,
        ]);

        return [
            'domain' => $domain,
            'root_domain' => $rootDomain,
            'version' => $versionClean,
            'zone_id' => $zoneId,
            'routes' => $results,
            'cache_purged' => $cachePurged,
        ];
    }

    /**
     * Purge all cache for a domain.
     *
     * @param string $domain
     * @return array<string, mixed>
     * @throws Exception
     */
    public function purgeCache(string $domain): array
    {
        $zoneId = $this->client->getZoneId($domain);
        return $this->client->purgeCache($zoneId, true);
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
        $response = $this->client->client()->get("/zones/{$zoneId}/workers/routes");

        if (! $response->successful()) {
            $this->client->handleApiError("Failed to fetch worker routes for zone {$zoneId}", $response);
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

        $response = $this->client->client()->post("/zones/{$zoneId}/workers/routes", $payload);

        if (! $response->successful()) {
            $this->client->handleApiError("Failed to create worker route [{$pattern}] for script [{$script}]", $response);
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

        $response = $this->client->client()->put("/zones/{$zoneId}/workers/routes/{$routeId}", $payload);

        if (! $response->successful()) {
            $this->client->handleApiError("Failed to update worker route [{$routeId}] to script [{$script}]", $response);
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
            $response = $this->client->client()->delete("/zones/{$zoneId}/workers/routes/{$routeId}");
            return $response->successful();
        } catch (Exception $e) {
            Log::warning("Failed to delete worker route {$routeId}: " . $e->getMessage());
            return false;
        }
    }
}
