<?php

namespace App\Observers;

use App\Models\Deployment;
use App\Services\Cache\ApplicationCache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class DeploymentObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private ApplicationCache $cache,
    ) {}

    public function saved(Deployment $deployment): void
    {
        $this->cache->invalidateDeploymentStatistics();
    }

    public function deleted(Deployment $deployment): void
    {
        $this->cache->invalidateDeploymentStatistics();
    }
}
