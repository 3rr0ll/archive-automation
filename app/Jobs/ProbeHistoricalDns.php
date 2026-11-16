<?php

namespace App\Jobs;

use App\Enums\DeploymentStatus;
use App\Models\Deployment;
use App\Services\ApiUsage\ApiUsageContext;
use App\Services\Deployment\ArchiveDeploymentService;
use App\Services\Prober\DnsProbeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProbeHistoricalDns implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(
        public Deployment $deployment
    ) {
        $this->onQueue('probing');
    }

    public function handle(
        DnsProbeService $dnsProbeService,
        ArchiveDeploymentService $deploymentService,
        ?ApiUsageContext $apiUsageContext = null
    ): void {
        $context = [
            'deployment_id' => $this->deployment->id,
            'domain' => $this->deployment->domain,
            'job' => self::class,
            'job_attempt' => $this->attempts(),
        ];
        Log::shareContext($context);

        try {
            ($apiUsageContext ?? app(ApiUsageContext::class))->forDeployment(
                (int) $this->deployment->getKey(),
                fn () => $this->probeAndDispatch($dnsProbeService, $deploymentService),
            );
        } finally {
            Log::withoutContext(array_keys($context));
        }
    }

    private function probeAndDispatch(
        DnsProbeService $dnsProbeService,
        ArchiveDeploymentService $deploymentService
    ): void {
        $this->deployment->refresh();

        if (in_array($this->deployment->status, [DeploymentStatus::Cancelled, DeploymentStatus::Completed], true)) {
            return;
        }

        $rawIps = (string) $this->deployment->historical_ips;
        preg_match_all('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $rawIps, $matches);
        $ips = array_values(array_unique($matches[0] ?? []));

        if (empty($ips)) {
            $this->fallbackToRuncloud('No valid historical IP addresses provided. Proceeding with fallback deployment.');

            return;
        }

        $this->deployment->update([
            'current_step' => 'probing_historical_ips',
            'status' => DeploymentStatus::Processing,
        ]);
        $this->deployment->logs()->create([
            'step_name' => 'probing_historical_ips',
            'status' => 'in_progress',
            'message' => sprintf('Probing %d historical IP address(es) for live related content.', count($ips)),
        ]);

        $workingIpData = null;

        try {
            $probeResult = $dnsProbeService->probeMultiple(
                $this->deployment->domain,
                $ips,
                ['http', 'https'],
                10
            );

            $summaries = $probeResult['summaries'] ?? [];
            $matchingCandidate = null;

            foreach ($summaries as $summary) {
                if (! empty($summary['is_parking'])) {
                    continue;
                }

                if (! empty($summary['is_working']) && ! empty($summary['is_content_related'])) {
                    $matchingCandidate = $summary;
                    break;
                }
            }

            if (! $matchingCandidate) {
                foreach ($summaries as $summary) {
                    if (! empty($summary['is_parking'])) {
                        continue;
                    }
                    if (($summary['score'] ?? 0) >= 8 || ($summary['verdict'] ?? '') === 'Likely old site') {
                        $matchingCandidate = $summary;
                        break;
                    }
                }
            }

            if ($matchingCandidate) {
                $workingIp = $matchingCandidate['ip'];
                $title = $matchingCandidate['title'] ?? 'N/A';

                $this->deployment->logs()->create([
                    'step_name' => 'probing_historical_ips',
                    'status' => 'success',
                    'message' => sprintf(
                        'Found working historical IP %s with related content (Title: "%s", Score: %s).',
                        $workingIp,
                        $title,
                        $matchingCandidate['score'] ?? 0
                    ),
                ]);

                $hasSitemap = $dnsProbeService->checkSitemapExists($this->deployment->domain, $workingIp);

                $this->deployment->logs()->create([
                    'step_name' => 'probing_historical_ips',
                    'status' => 'success',
                    'message' => $hasSitemap
                        ? 'Sitemap detected on working IP (https://'.$this->deployment->domain.'/sitemap.xml). It will be submitted to Google Search Console.'
                        : 'No sitemap found on working IP. Sitemap submission to Google Search Console will be skipped.',
                ]);

                $workingIpData = [
                    'ip' => $workingIp,
                    'has_sitemap' => $hasSitemap,
                ];
            }
        } catch (Throwable $e) {
            Log::warning('Error probing historical IPs', [
                'deployment_id' => $this->deployment->id,
                'domain' => $this->deployment->domain,
                'error' => $e->getMessage(),
            ]);

            $this->deployment->logs()->create([
                'step_name' => 'probing_historical_ips',
                'status' => 'warning',
                'message' => 'Historical IP probing encountered an error ('.$e->getMessage().'). Falling back to RunCloud deployment.',
            ]);
        }

        if ($workingIpData !== null) {
            $this->deployment->update([
                'working_ip' => $workingIpData['ip'],
                'deployment_type' => 'redirect',
                'archive_source' => 'redirect',
                'has_sitemap' => $workingIpData['has_sitemap'],
            ]);

            // Directly complete the Cloudflare redirect & Search Console setup
            $deploymentService->deploy($this->deployment);
        } else {
            $this->fallbackToRuncloud('No working IP with content related to the domain was found. Falling back to RunCloud server deployment.');
        }
    }

    private function fallbackToRuncloud(string $reason): void
    {
        $this->deployment->refresh();

        if (in_array($this->deployment->status, [DeploymentStatus::Cancelled, DeploymentStatus::Completed], true)) {
            return;
        }

        $this->deployment->logs()->create([
            'step_name' => 'probing_historical_ips',
            'status' => 'warning',
            'message' => $reason,
        ]);

        $isUpload = $this->deployment->archive_source === 'upload' || filled($this->deployment->archive_path);

        if ($isUpload) {
            $this->deployment->update([
                'current_step' => 'archive_ready',
                'status' => DeploymentStatus::Pending,
            ]);
            DeployArchivedWebsite::dispatch($this->deployment);
        } else {
            $this->deployment->update([
                'current_step' => 'waiting_for_archive',
                'status' => DeploymentStatus::Pending,
            ]);
            WaitForWaybackArchive::dispatch($this->deployment);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Historical IP probing job failed.', [
            'deployment_id' => $this->deployment->id,
            'domain' => $this->deployment->domain,
            'job' => self::class,
            'exception' => $exception?->getMessage(),
        ]);

        $this->fallbackToRuncloud('Historical IP probing job failed unexpectedly. Continuing to RunCloud deployment.');
    }
}
