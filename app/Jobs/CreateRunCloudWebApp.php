<?php

namespace App\Jobs;

use App\Models\Deployment;
use App\Services\RunCloud\RunCloudService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateRunCloudWebApp implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Deployment $deployment
    ) {}

    public function handle(RunCloudService $runCloudService): void
    {
        $runCloudService->createWebApplication(
            $this->deployment->server_id,
            $this->deployment->domain
        );
    }
}
