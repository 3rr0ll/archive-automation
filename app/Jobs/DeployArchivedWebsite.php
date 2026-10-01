<?php

namespace App\Jobs;

use App\Models\Deployment;
use App\Services\InternetArchive\InternetArchiveService;
use App\Services\RunCloud\RunCloudService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeployArchivedWebsite implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Deployment $deployment
    ) {}

    public function handle(
        InternetArchiveService $archiveService,
        RunCloudService $runCloudService
    ): void {
        // Multi-step archive extraction and web deployment workflow
    }
}
