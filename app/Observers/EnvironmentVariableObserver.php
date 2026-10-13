<?php

namespace App\Observers;

use App\Models\EnvironmentVariable;
use App\Services\Cache\ApplicationCache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class EnvironmentVariableObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private ApplicationCache $cache,
    ) {}

    public function created(EnvironmentVariable $variable): void
    {
        $this->invalidateForKeys([$variable->key]);
    }

    public function updated(EnvironmentVariable $variable): void
    {
        $this->invalidateForKeys([
            $variable->key,
            (string) $variable->getOriginal('key'),
        ]);
    }

    public function deleted(EnvironmentVariable $variable): void
    {
        $this->invalidateForKeys([$variable->key]);
    }

    /**
     * @param  list<string>  $keys
     */
    private function invalidateForKeys(array $keys): void
    {
        foreach (array_unique($keys) as $key) {
            if (str_starts_with($key, 'CLOUDFLARE_')) {
                $this->cache->invalidateCloudflare();
            }

            if (str_starts_with($key, 'RUNCLOUD_')) {
                $this->cache->invalidateRunCloud();
            }

            if ($key === 'INTERNET_ARCHIVE_SETTINGS') {
                $this->cache->forgetInternetArchiveSettings();
            }
        }
    }
}
