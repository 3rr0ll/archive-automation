<?php

namespace App\Observers;

use App\Models\Server;
use App\Services\Cache\ApplicationCache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ServerObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private ApplicationCache $cache,
    ) {}

    public function saved(Server $server): void
    {
        $this->invalidate();
    }

    public function deleted(Server $server): void
    {
        $this->invalidate();
    }

    private function invalidate(): void
    {
        $this->cache->forgetLocalServers();
        $this->cache->invalidateRunCloud();
    }
}
