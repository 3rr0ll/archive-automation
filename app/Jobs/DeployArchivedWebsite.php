<?php

namespace App\Jobs;

use App\Models\Deployment;
use App\Services\Deployment\ArchiveDeploymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeployArchivedWebsite implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Deployment $deployment
    ) {}

    public function handle(ArchiveDeploymentService $deploymentService): void
    {
        $deploymentService->deploy($this->deployment);
    }
}
