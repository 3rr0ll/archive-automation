<?php

namespace App\Services\InternetArchive;

use App\Models\EnvironmentVariable;
use App\Services\Cache\ApplicationCache;

class InternetArchiveSettings
{
    public function __construct(
        private ApplicationCache $cache,
    ) {}

    /**
     * @return array{allowed_hosts: list<string>, timeout: int, poll_delay: int, max_attempts: int, wayback_url: string}
     */
    public function all(): array
    {
        return $this->cache->rememberInternetArchiveSettings(fn (): array => $this->resolve());
    }

    /**
     * @return array{allowed_hosts: list<string>, timeout: int, poll_delay: int, max_attempts: int, wayback_url: string}
     */
    private function resolve(): array
    {
        $defaults = [
            'allowed_hosts' => array_values(config('services.internet_archive.allowed_archive_hosts', [])),
            'timeout' => (int) config('services.internet_archive.timeout', 20),
            'poll_delay' => (int) config('services.internet_archive.poll_delay', 60),
            'max_attempts' => (int) config('services.internet_archive.max_attempts', 1440),
            'wayback_url' => (string) config('services.internet_archive.wayback_url', 'https://archive.org/wayback/available'),
        ];

        // Check for dedicated WAYBACK_URL or WAYBACK_API_URL in EnvironmentVariable
        $waybackVar = EnvironmentVariable::query()
            ->whereIn('key', ['WAYBACK_URL', 'WAYBACK_API_URL', 'WAYBACK_LINK'])
            ->latest('updated_at')
            ->latest('id')
            ->first();

        if ($waybackVar !== null && filled($waybackVar->value)) {
            $defaults['wayback_url'] = trim((string) $waybackVar->value);
        }

        $credential = $this->databaseCredential();

        if ($credential === null) {
            return $defaults;
        }

        $settings = json_decode((string) $credential->value, true);

        if (! is_array($settings)) {
            return $defaults;
        }

        return [
            'allowed_hosts' => $this->allowedHosts($settings['allowed_hosts'] ?? $defaults['allowed_hosts']),
            'timeout' => $this->positiveInteger($settings['timeout'] ?? null, $defaults['timeout']),
            'poll_delay' => $this->positiveInteger($settings['poll_delay'] ?? null, $defaults['poll_delay']),
            'max_attempts' => $this->positiveInteger($settings['max_attempts'] ?? null, $defaults['max_attempts']),
            'wayback_url' => filled($settings['wayback_url'] ?? null)
                ? trim((string) $settings['wayback_url'])
                : $defaults['wayback_url'],
        ];
    }

    public function hasDatabaseOverride(): bool
    {
        return $this->databaseCredential() !== null;
    }

    private function databaseCredential(): ?EnvironmentVariable
    {
        return EnvironmentVariable::query()
            ->where('key', 'INTERNET_ARCHIVE_SETTINGS')
            ->latest('updated_at')
            ->latest('id')
            ->first();
    }

    /**
     * @return list<string>
     */
    private function allowedHosts(mixed $hosts): array
    {
        if (! is_array($hosts)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $host): string => strtolower(trim((string) $host)),
            $hosts,
        ))));
    }

    private function positiveInteger(mixed $value, int $fallback): int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $value === false ? $fallback : $value;
    }
}
