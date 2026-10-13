<?php

namespace App\Services\InternetArchive;

use App\Models\EnvironmentVariable;
use App\Models\User;
use App\Services\Cache\ApplicationCache;
use App\Services\Logging\AuditLogger;
use Illuminate\Support\Facades\DB;

class InternetArchiveSettingsService
{
    public function __construct(
        private InternetArchiveSettings $settings,
        private ApplicationCache $cache,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{allowed_hosts: list<string>, timeout: int, poll_delay: int, max_attempts: int}  $data
     * @return array{allowed_hosts: list<string>, timeout: int, poll_delay: int, max_attempts: int}
     */
    public function update(User $actor, array $data): array
    {
        $payload = [
            'allowed_hosts' => array_values(array_unique(array_map(
                fn (string $host): string => strtolower(trim($host)),
                $data['allowed_hosts'],
            ))),
            'timeout' => $data['timeout'],
            'poll_delay' => $data['poll_delay'],
            'max_attempts' => $data['max_attempts'],
        ];

        DB::transaction(function () use ($actor, $payload): void {
            User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();

            $credential = EnvironmentVariable::query()
                ->where('key', 'INTERNET_ARCHIVE_SETTINGS')
                ->latest('updated_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();
            $attributes = [
                'user_id' => $actor->id,
                'type' => 'config',
                'environments' => 'Production',
                'note' => 'Global Internet Archive settings',
                'value' => json_encode($payload, JSON_THROW_ON_ERROR),
            ];

            if ($credential === null) {
                $credential = EnvironmentVariable::create([
                    ...$attributes,
                    'key' => 'INTERNET_ARCHIVE_SETTINGS',
                ]);
            } else {
                $credential->update($attributes);
            }

            EnvironmentVariable::query()
                ->where('key', 'INTERNET_ARCHIVE_SETTINGS')
                ->whereKeyNot($credential->getKey())
                ->delete();
        });

        $this->cache->forgetInternetArchiveSettings();
        $this->auditLogger->record('internet-archive-settings.updated', context: [
            'actor_id' => $actor->id,
            'allowed_host_count' => count($payload['allowed_hosts']),
            'timeout' => $payload['timeout'],
            'poll_delay' => $payload['poll_delay'],
            'max_attempts' => $payload['max_attempts'],
        ]);

        return $this->settings->all();
    }
}
