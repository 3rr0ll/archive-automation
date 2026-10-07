<?php

namespace App\Services\InternetArchive;

use App\Models\ApiCredential;

class InternetArchiveSettings
{
    /**
     * @return array{allowed_hosts: list<string>, timeout: int, poll_delay: int, max_attempts: int}
     */
    public function all(): array
    {
        $defaults = [
            'allowed_hosts' => array_values(config('services.internet_archive.allowed_archive_hosts', [])),
            'timeout' => (int) config('services.internet_archive.timeout', 20),
            'poll_delay' => (int) config('services.internet_archive.poll_delay', 60),
            'max_attempts' => (int) config('services.internet_archive.max_attempts', 1440),
        ];

        $credential = ApiCredential::query()
            ->where('service', 'internet_archive')
            ->latest('updated_at')
            ->latest('id')
            ->first();

        if ($credential === null) {
            return $defaults;
        }

        $settings = json_decode((string) $credential->tokenData(), true);

        if (! is_array($settings)) {
            return $defaults;
        }

        return [
            'allowed_hosts' => $this->allowedHosts($settings['allowed_hosts'] ?? $defaults['allowed_hosts']),
            'timeout' => $this->positiveInteger($settings['timeout'] ?? null, $defaults['timeout']),
            'poll_delay' => $this->positiveInteger($settings['poll_delay'] ?? null, $defaults['poll_delay']),
            'max_attempts' => $this->positiveInteger($settings['max_attempts'] ?? null, $defaults['max_attempts']),
        ];
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
