<?php

namespace App\Jobs;

use App\Enums\DeploymentStatus;
use App\Models\Deployment;
use App\Services\ApiUsage\ApiUsageContext;
use App\Services\Cloudflare\CloudflareDnsService;
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
        CloudflareDnsService $cloudflareDnsService,
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
                fn () => $this->probeAndDispatch($dnsProbeService, $deploymentService, $cloudflareDnsService),
            );
        } finally {
            Log::withoutContext(array_keys($context));
        }
    }

    private function probeAndDispatch(
        DnsProbeService $dnsProbeService,
        ArchiveDeploymentService $deploymentService,
        CloudflareDnsService $cloudflareDnsService
    ): void {
        $this->deployment->refresh();

        if (in_array($this->deployment->status, [DeploymentStatus::Cancelled, DeploymentStatus::Completed], true)) {
            return;
        }

        $rawIps = (string) $this->deployment->historical_ips;
        $ips = $dnsProbeService->filterHistoricalIpsOneYearApart($rawIps);

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

            // 1. First priority: Working IP with HTTP 2xx on HTTPS and content related to domain
            foreach ($summaries as $summary) {
                if (! empty($summary['is_parking'])) {
                    continue;
                }

                $hasHttps2xx = ! empty($summary['is_https_2xx'])
                    || (is_numeric($summary['https'] ?? null) && (int) $summary['https'] >= 200 && (int) $summary['https'] < 300);

                if ($hasHttps2xx && ! empty($summary['is_content_related'])) {
                    $matchingCandidate = $summary;
                    break;
                }
            }

            // 2. Second priority: Any working 2xx (HTTP or HTTPS) with content related to domain
            if (! $matchingCandidate) {
                foreach ($summaries as $summary) {
                    if (! empty($summary['is_parking'])) {
                        continue;
                    }

                    if (! empty($summary['is_working']) && ! empty($summary['is_content_related'])) {
                        $matchingCandidate = $summary;
                        break;
                    }
                }
            }

            // 3. Third priority: Score >= 8 or verdict 'Likely old site' AND not parking/sale
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

            // 4. Fourth priority: If no working IP found, test Live (blocked) IPs one by one via Cloudflare
            if (! $matchingCandidate) {
                $blockedCandidates = array_values(array_filter(
                    $summaries,
                    fn (array $s) => ! empty($s['is_blocked']) || ($s['verdict'] ?? '') === 'Live (blocked)'
                ));

                if (! empty($blockedCandidates)) {
                    $this->deployment->logs()->create([
                        'step_name' => 'probing_historical_ips',
                        'status' => 'in_progress',
                        'message' => sprintf(
                            'No directly accessible working IP found. Testing %d Live (blocked) candidate IP(s) one by one via Cloudflare proxy...',
                            count($blockedCandidates)
                        ),
                    ]);

                    foreach ($blockedCandidates as $index => $candidate) {
                        $candidateIp = $candidate['ip'];

                        $this->deployment->refresh();
                        if (in_array($this->deployment->status, [DeploymentStatus::Cancelled, DeploymentStatus::Completed], true)) {
                            return;
                        }

                        $this->deployment->logs()->create([
                            'step_name' => 'probing_historical_ips',
                            'status' => 'in_progress',
                            'message' => sprintf('Assigning Cloudflare DNS to blocked IP [%s] (%d/%d) and verifying live content...', $candidateIp, $index + 1, count($blockedCandidates)),
                        ]);

                        try {
                            // Assign Cloudflare DNS A records (@ and www) proxied to the blocked IP
                            $cloudflareDnsService->setupRunCloudDns($this->deployment->domain, $candidateIp, true);

                            // Short pause to allow Cloudflare edge routing to take effect
                            sleep(3);

                            // Verify content through Cloudflare
                            $verifyResult = $dnsProbeService->verifyLiveDomainContent($this->deployment->domain, 10);

                            if ($verifyResult['is_working'] && $verifyResult['is_content_related']) {
                                $this->deployment->logs()->create([
                                    'step_name' => 'probing_historical_ips',
                                    'status' => 'success',
                                    'message' => sprintf(
                                        'Verified Live (blocked) IP [%s] is serving related content via Cloudflare (Title: "%s", Status: %d).',
                                        $candidateIp,
                                        $verifyResult['title'] ?? 'N/A',
                                        $verifyResult['status'] ?? 200
                                    ),
                                ]);

                                $candidate['title'] = $verifyResult['title'];
                                $candidate['score'] = max($candidate['score'] ?? 0, 8);
                                if (! empty($verifyResult['detected_version'])) {
                                    $candidate['detected_version'] = $verifyResult['detected_version'];
                                }
                                $matchingCandidate = $candidate;
                                break;
                            } else {
                                $this->deployment->logs()->create([
                                    'step_name' => 'probing_historical_ips',
                                    'status' => 'warning',
                                    'message' => sprintf(
                                        'Blocked IP [%s] did not serve valid related content through Cloudflare (Status: %s, Error: %s). Checking next candidate...',
                                        $candidateIp,
                                        $verifyResult['status'] ?? 'None',
                                        $verifyResult['error'] ?? 'Unrelated or empty content'
                                    ),
                                ]);
                            }
                        } catch (Throwable $cfEx) {
                            $this->deployment->logs()->create([
                                'step_name' => 'probing_historical_ips',
                                'status' => 'warning',
                                'message' => sprintf('Error verifying blocked IP [%s] via Cloudflare: %s', $candidateIp, $cfEx->getMessage()),
                            ]);
                        }
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

                $detectedVersion = $matchingCandidate['detected_version'] ?? null;
                if (! empty($detectedVersion)) {
                    $this->deployment->logs()->create([
                        'step_name' => 'probing_historical_ips',
                        'status' => 'success',
                        'message' => sprintf(
                            'Detected working live site version as [%s]. Cloudflare redirect Worker will be configured for %s.',
                            $detectedVersion,
                            $detectedVersion
                        ),
                    ]);
                }

                $workingIpData = [
                    'ip' => $workingIp,
                    'has_sitemap' => $hasSitemap,
                    'version' => $detectedVersion,
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
            $updateData = [
                'working_ip' => $workingIpData['ip'],
                'deployment_type' => 'redirect',
                'archive_source' => 'redirect',
                'has_sitemap' => $workingIpData['has_sitemap'],
            ];

            if (! empty($workingIpData['version'])) {
                $updateData['version'] = $workingIpData['version'];
            }

            $this->deployment->update($updateData);

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
