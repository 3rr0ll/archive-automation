<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Compatibility handler for jobs queued by older application versions.
 *
 * General website URLs must not be sent to Google's Indexing API. New
 * deployments submit a sitemap instead, and any existing queued instances of
 * this job complete safely without publishing URL notifications.
 */
class RequestGoogleIndexing implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $domain,
        public string $sitemapUrl,
        public int $rounds = 0,
        public ?int $deploymentId = null,
    ) {}

    public function handle(): void
    {
        Log::notice('Skipped deprecated Google Indexing API job; sitemap submission is used instead.', [
            'domain' => $this->domain,
            'deployment_id' => $this->deploymentId,
        ]);
    }
}
