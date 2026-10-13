<?php

namespace App\Services\Cache;

use Closure;
use Illuminate\Support\Facades\Cache;

class ApplicationCache
{
    private const CLOUDFLARE_VERSION_KEY = 'cache-version:cloudflare';

    private const RUNCLOUD_VERSION_KEY = 'cache-version:runcloud';

    private const DEPLOYMENT_STATISTICS_VERSION_KEY = 'cache-version:deployment-statistics';

    private const INTERNET_ARCHIVE_SETTINGS_KEY = 'internet-archive:settings';

    private const LOCAL_SERVERS_KEY = 'servers:local-list';

    public function rememberCloudflareZone(string $domain, Closure $callback): string
    {
        return (string) Cache::remember(
            $this->cloudflareKey('zone:'.$this->identifier($domain)),
            now()->addDay(),
            $callback,
        );
    }

    public function forgetCloudflareZone(string $domain): void
    {
        Cache::forget($this->cloudflareKey('zone:'.$this->identifier($domain)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rememberCloudflareDnsRecords(string $zoneId, Closure $callback): array
    {
        return Cache::remember(
            $this->cloudflareKey('dns-records:'.$this->identifier($zoneId)),
            now()->addSeconds(30),
            $callback,
        );
    }

    public function forgetCloudflareDnsRecords(string $zoneId): void
    {
        Cache::forget($this->cloudflareKey('dns-records:'.$this->identifier($zoneId)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rememberCloudflareWorkerRoutes(string $zoneId, Closure $callback): array
    {
        return Cache::remember(
            $this->cloudflareKey('worker-routes:'.$this->identifier($zoneId)),
            now()->addSeconds(30),
            $callback,
        );
    }

    public function forgetCloudflareWorkerRoutes(string $zoneId): void
    {
        Cache::forget($this->cloudflareKey('worker-routes:'.$this->identifier($zoneId)));
    }

    public function invalidateCloudflare(): void
    {
        $this->bumpVersion(self::CLOUDFLARE_VERSION_KEY);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rememberRunCloudServers(Closure $callback): array
    {
        return Cache::remember(
            $this->runCloudKey('servers'),
            now()->addMinutes(5),
            $callback,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rememberRunCloudServer(int $serverId, Closure $callback): array
    {
        return Cache::remember(
            $this->runCloudKey("server:{$serverId}"),
            now()->addMinutes(30),
            $callback,
        );
    }

    public function rememberRunCloudServerIp(int $serverId, Closure $callback): string
    {
        return (string) Cache::remember(
            $this->runCloudKey("server-ip:{$serverId}"),
            now()->addDays(7),
            $callback,
        );
    }

    public function rememberRunCloudSystemUserId(int $serverId, Closure $callback): int
    {
        return (int) Cache::remember(
            $this->runCloudKey("system-user:{$serverId}"),
            now()->addDays(30),
            $callback,
        );
    }

    public function invalidateRunCloud(): void
    {
        $this->bumpVersion(self::RUNCLOUD_VERSION_KEY);
    }

    /**
     * @return array{allowed_hosts: list<string>, timeout: int, poll_delay: int, max_attempts: int}
     */
    public function rememberInternetArchiveSettings(Closure $callback): array
    {
        return Cache::remember(
            self::INTERNET_ARCHIVE_SETTINGS_KEY,
            now()->addMinutes(30),
            $callback,
        );
    }

    public function forgetInternetArchiveSettings(): void
    {
        Cache::forget(self::INTERNET_ARCHIVE_SETTINGS_KEY);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function rememberDeploymentStatistics(array $filters, Closure $callback): array
    {
        ksort($filters);
        $filterHash = hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR));

        return Cache::remember(
            $this->versionedKey(self::DEPLOYMENT_STATISTICS_VERSION_KEY, "deployment-statistics:{$filterHash}"),
            now()->addSeconds(10),
            $callback,
        );
    }

    public function invalidateDeploymentStatistics(): void
    {
        $this->bumpVersion(self::DEPLOYMENT_STATISTICS_VERSION_KEY);
    }

    public function rememberLocalServers(Closure $callback): mixed
    {
        return Cache::remember(self::LOCAL_SERVERS_KEY, now()->addMinutes(5), $callback);
    }

    public function forgetLocalServers(): void
    {
        Cache::forget(self::LOCAL_SERVERS_KEY);
    }

    private function cloudflareKey(string $suffix): string
    {
        return $this->versionedKey(self::CLOUDFLARE_VERSION_KEY, 'cloudflare:'.$suffix);
    }

    private function runCloudKey(string $suffix): string
    {
        return $this->versionedKey(self::RUNCLOUD_VERSION_KEY, 'runcloud:'.$suffix);
    }

    private function versionedKey(string $versionKey, string $key): string
    {
        return $key.':v'.$this->version($versionKey);
    }

    private function version(string $key): int
    {
        return (int) Cache::rememberForever($key, fn (): int => 1);
    }

    private function bumpVersion(string $key): void
    {
        Cache::add($key, 1);
        Cache::increment($key);
    }

    private function identifier(string $value): string
    {
        return hash('sha256', strtolower(trim($value)));
    }
}
