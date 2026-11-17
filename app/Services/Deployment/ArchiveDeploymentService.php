<?php

namespace App\Services\Deployment;

use App\Enums\DeploymentStatus;
use App\Models\Deployment;
use App\Services\ApiUsage\ApiUsageContext;
use App\Services\Cloudflare\CloudflareDnsService;
use App\Services\Cloudflare\CloudflareWorkerService;
use App\Services\GoogleSearchConsole\GoogleSearchConsoleService;
use App\Services\InternetArchive\InternetArchiveService;
use App\Services\Prober\DnsProbeService;
use App\Services\RunCloud\RunCloudService;
use App\Services\Sftp\SftpArchiveService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ArchiveDeploymentService
{
    private ApiUsageContext $apiUsageContext;

    public function __construct(
        private InternetArchiveService $archiveService,
        private RunCloudService $runCloudService,
        private CloudflareWorkerService $cloudflareWorkerService,
        private GoogleSearchConsoleService $searchConsoleService,
        private SftpArchiveService $sftpArchiveService,
        private ?CloudflareDnsService $cloudflareDnsService = null,
        private ?DnsProbeService $dnsProbeService = null,
        ?ApiUsageContext $apiUsageContext = null,
    ) {
        $this->cloudflareDnsService = $cloudflareDnsService ?? app(CloudflareDnsService::class);
        $this->dnsProbeService = $dnsProbeService ?? app(DnsProbeService::class);
        $this->apiUsageContext = $apiUsageContext ?? app(ApiUsageContext::class);
    }

    public function deploy(Deployment $deployment): Deployment
    {
        return $this->apiUsageContext->forDeployment(
            (int) $deployment->getKey(),
            fn (): Deployment => $this->performDeployment($deployment),
        );
    }

    private function performDeployment(Deployment $deployment): Deployment
    {
        $deployment->refresh();

        if (in_array($deployment->status, [DeploymentStatus::Cancelled, DeploymentStatus::Completed], true)) {
            return $deployment;
        }

        try {
            if (($deployment->deployment_type ?? 'full') === 'redirect' || $deployment->archive_source === 'redirect') {
                return $this->performRedirectDeployment($deployment);
            }

            $version = $deployment->version instanceof \BackedEnum
                ? $deployment->version->value
                : (string) $deployment->version;

            $archiveExists = filled($deployment->archive_path)
                && Storage::disk('local')->exists($deployment->archive_path)
                && Storage::disk('local')->size($deployment->archive_path) > 0;

            if ($deployment->archive_source === 'upload') {
                $this->transition($deployment, 'preparing_archive', 'Preparing the uploaded ZIP.', DeploymentStatus::Processing);

                if (! $archiveExists) {
                    throw new RuntimeException('The uploaded ZIP is no longer available.');
                }
            } else {
                $archiveRelativePath = filled($deployment->archive_path)
                    ? $deployment->archive_path
                    : "deployments/{$deployment->id}/archive.zip";

                if ($archiveExists) {
                    $this->transition($deployment, 'downloading_archive', 'Reusing previously downloaded archive ZIP.', DeploymentStatus::Processing);
                    $deployment->logs()->create([
                        'step_name' => 'downloading_archive',
                        'status' => 'success',
                        'message' => sprintf(
                            'Reusing the already downloaded archive ZIP (%d bytes).',
                            Storage::disk('local')->size($archiveRelativePath),
                        ),
                    ]);
                } else {
                    $this->transition($deployment, 'downloading_archive', 'Downloading the archived website.', DeploymentStatus::Processing);

                    $archiveAbsolutePath = Storage::disk('local')->path($archiveRelativePath);

                    if (blank($deployment->archive_url)) {
                        throw new RuntimeException('The archive download URL is not available.');
                    }

                    $downloadSuccess = $this->archiveService->downloadArchiveFromUrl(
                        $deployment->archive_url,
                        $archiveAbsolutePath,
                    );

                    if (! $downloadSuccess
                        || ! Storage::disk('local')->exists($archiveRelativePath)
                        || Storage::disk('local')->size($archiveRelativePath) === 0) {
                        throw new RuntimeException('The archived website could not be downloaded.');
                    }

                    $deployment->update(['archive_path' => $archiveRelativePath]);
                    $deployment->logs()->create([
                        'step_name' => 'downloading_archive',
                        'status' => 'success',
                        'message' => sprintf(
                            'Downloaded the archive ZIP (%d bytes).',
                            Storage::disk('local')->size($archiveRelativePath),
                        ),
                    ]);
                }
            }

            $deployment->refresh();
            if ($deployment->status === DeploymentStatus::Cancelled) {
                return $deployment;
            }

            $this->transition($deployment, 'provisioning_web_app', 'Provisioning the RunCloud web application.');
            $serverIp = $deployment->server?->ip_address;

            $result = filled($serverIp)
                ? $this->runCloudService->createEmptyWebApplication(
                    $deployment->domain,
                    (int) $deployment->server_id,
                    $serverIp,
                )
                : $this->runCloudService->createEmptyWebApplication(
                    $deployment->domain,
                    (int) $deployment->server_id,
                );

            $runCloudWebAppId = (int) ($result['web_app_id'] ?? $result['app']['id'] ?? 0);
            $runCloudWebAppName = $result['web_app_name'] ?? $result['app']['name'] ?? null;

            $deployment->update([
                'runcloud_web_app_id' => $runCloudWebAppId > 0 ? $runCloudWebAppId : null,
                'runcloud_web_app_name' => is_string($runCloudWebAppName) ? $runCloudWebAppName : null,
            ]);

            if (! empty($result['already_exists'])) {
                $canResumeDeployment = $deployment->logs()
                    ->whereIn('step_name', ['provisioning_web_app', 'uploading_and_extracting_archive'])
                    ->exists()
                    || Deployment::query()
                        ->whereKeyNot($deployment->getKey())
                        ->where('domain', $deployment->domain)
                        ->where('server_id', $deployment->server_id)
                        ->whereIn('status', [
                            DeploymentStatus::Completed->value,
                            DeploymentStatus::Failed->value,
                        ])
                        ->exists();

                if (! $canResumeDeployment) {
                    throw new RuntimeException('A RunCloud web application already exists for this domain.');
                }

                $result['web_app_name'] = $this->runCloudService->generateWebAppName($deployment->domain);
                $deployment->logs()->create([
                    'step_name' => 'provisioning_web_app',
                    'status' => 'success',
                    'message' => 'Reusing the RunCloud web application managed by an earlier deployment attempt.',
                ]);
            }

            $deployment->refresh();
            if ($deployment->status === DeploymentStatus::Cancelled) {
                return $deployment;
            }

            if (blank($deployment->archive_path)
                || ! Storage::disk('local')->exists($deployment->archive_path)
                || Storage::disk('local')->size($deployment->archive_path) === 0) {
                throw new RuntimeException('The local archive ZIP is unavailable for server deployment.');
            }

            $archiveAlreadyExtracted = $deployment->logs()
                ->where('step_name', 'uploading_and_extracting_archive')
                ->where('status', 'success')
                ->exists();

            if ($archiveAlreadyExtracted) {
                $deployment->logs()->create([
                    'step_name' => 'uploading_and_extracting_archive',
                    'status' => 'success',
                    'message' => 'Archive files were already uploaded and extracted in a previous attempt. Skipping.',
                ]);
            } else {
                $this->deployArchiveToServer($deployment, $result, $serverIp);
            }

            $deployment->refresh();
            if ($deployment->status === DeploymentStatus::Cancelled) {
                return $deployment;
            }

            $workersAlreadyConfigured = $deployment->logs()
                ->where('step_name', 'configuring_cloudflare_workers')
                ->where('status', 'success')
                ->exists();

            if ($workersAlreadyConfigured) {
                $deployment->logs()->create([
                    'step_name' => 'configuring_cloudflare_workers',
                    'status' => 'success',
                    'message' => 'Cloudflare Worker routes were already configured in a previous attempt. Skipping.',
                ]);
            } else {
                $this->transition(
                    $deployment,
                    'configuring_cloudflare_workers',
                    'Assigning the Cloudflare Worker routes.',
                );

                $this->cloudflareWorkerService->changeWorkers($deployment->domain, $version);
            }

            $deployment->refresh();
            if ($deployment->status === DeploymentStatus::Cancelled) {
                return $deployment;
            }

            if ($this->searchConsoleService->isEnabled()) {
                $this->registerWithSearchConsole($deployment);
            }

            $deployment->update([
                'status' => DeploymentStatus::Completed,
                'current_step' => 'completed',
            ]);
            $deployment->logs()->create([
                'step_name' => 'completed',
                'status' => 'success',
                'message' => 'Archived website deployment completed.',
            ]);

            return $deployment->refresh();
        } catch (Throwable $exception) {
            $deployment->refresh();

            Log::error('Archived website deployment failed.', [
                'deployment_id' => $deployment->id,
                'domain' => $deployment->domain,
                'server_id' => $deployment->server_id,
                'current_step' => $deployment->current_step,
                'exception' => $exception::class,
                'error' => $exception->getMessage(),
            ]);

            if ($deployment->status !== DeploymentStatus::Cancelled) {
                $deployment->update([
                    'status' => DeploymentStatus::Failed,
                    'current_step' => 'deployment_failed',
                ]);
                $deployment->logs()->create([
                    'step_name' => 'deployment_failed',
                    'status' => 'error',
                    'message' => $this->failureMessage($exception),
                ]);
            }

            throw $exception;
        }
    }

    private function performRedirectDeployment(Deployment $deployment): Deployment
    {
        $version = $deployment->version instanceof \BackedEnum
            ? $deployment->version->value
            : (string) $deployment->version;

        // If a working historical IP is present, update @ and www A records in Cloudflare DNS
        $workingIp = $deployment->working_ip;
        if (! empty($workingIp)) {
            $this->transition($deployment, 'configuring_dns', sprintf('Configuring Cloudflare DNS A records (@ and www) to %s.', $workingIp), DeploymentStatus::Processing);

            try {
                $this->cloudflareDnsService->setupRunCloudDns($deployment->domain, $workingIp, true);

                $deployment->logs()->create([
                    'step_name' => 'configuring_dns',
                    'status' => 'success',
                    'message' => sprintf('Cloudflare DNS A records for @ and www updated to %s (proxied).', $workingIp),
                ]);
            } catch (Throwable $dnsException) {
                Log::warning('Failed to configure Cloudflare DNS A records for redirect deployment.', [
                    'deployment_id' => $deployment->id,
                    'domain' => $deployment->domain,
                    'working_ip' => $workingIp,
                    'error' => $dnsException->getMessage(),
                ]);

                $deployment->logs()->create([
                    'step_name' => 'configuring_dns',
                    'status' => 'warning',
                    'message' => sprintf('Cloudflare DNS update to %s encountered a warning: %s', $workingIp, $dnsException->getMessage()),
                ]);
            }
        }

        $this->transition($deployment, 'configuring_cloudflare_workers', 'Assigning Cloudflare redirect Worker routes.', DeploymentStatus::Processing);

        // Configure Cloudflare Worker redirect routes:
        // If version is www: both www and non-www route to CLOUDFLARE_WORKER_WWW_REDIRECT
        // If version is non-www: both non-www and www route to CLOUDFLARE_WORKER_ROOT_REDIRECT
        $this->cloudflareWorkerService->changeRedirectOnlyWorkers($deployment->domain, $version);

        $deployment->logs()->create([
            'step_name' => 'configuring_cloudflare_workers',
            'status' => 'success',
            'message' => sprintf(
                'Cloudflare Worker redirect routes configured for %s version.',
                $version
            ),
        ]);

        $deployment->refresh();
        if ($deployment->status === DeploymentStatus::Cancelled) {
            return $deployment;
        }

        if ($this->searchConsoleService->isEnabled()) {
            $hasSitemap = (bool) ($deployment->has_sitemap ?? true);
            $this->transition(
                $deployment,
                'registering_search_console',
                $hasSitemap
                    ? 'Verifying the domain and submitting its sitemap to Google Search Console.'
                    : 'Verifying the domain with Google Search Console (sitemap skipped).'
            );

            try {
                $sitemapHost = $version === 'www'
                    ? 'www.'.$deployment->domain
                    : $deployment->domain;

                $this->searchConsoleService->registerDomain(
                    domain: $deployment->domain,
                    sitemapUrl: "https://{$sitemapHost}/sitemap.xml",
                    verifyOwnership: true,
                    requestIndexing: true,
                    submitSitemap: $hasSitemap,
                );

                $deployment->logs()->create([
                    'step_name' => 'registering_search_console',
                    'status' => 'success',
                    'message' => $hasSitemap
                        ? 'Google Search Console registration and sitemap submission completed.'
                        : 'Google Search Console registration completed (sitemap submission skipped).',
                ]);
            } catch (Throwable $exception) {
                Log::warning('Google Search Console registration failed for redirect deployment.', [
                    'deployment_id' => $deployment->id,
                    'domain' => $deployment->domain,
                    'error' => $exception->getMessage(),
                ]);
                $deployment->logs()->create([
                    'step_name' => 'registering_search_console',
                    'status' => 'error',
                    'message' => 'Google Search Console registration failed: '.$exception->getMessage(),
                ]);
            }
        }

        $deployment->update([
            'status' => DeploymentStatus::Completed,
            'current_step' => 'completed',
        ]);
        $deployment->logs()->create([
            'step_name' => 'completed',
            'status' => 'success',
            'message' => 'Redirect deployment completed successfully (RunCloud skipped, Cloudflare and Search Console configured).',
        ]);

        return $deployment->refresh();
    }

    /**
     * Probe historical IPs for a domain to check if any IP is alive and serving related content.
     *
     * @return array{ip: string, has_sitemap: bool}|null
     */
    private function resolveWorkingHistoricalIp(Deployment $deployment): ?array
    {
        $rawIps = (string) $deployment->historical_ips;
        $ips = $this->dnsProbeService->filterHistoricalIpsOneYearApart($rawIps);

        if (empty($ips)) {
            return null;
        }

        $this->transition(
            $deployment,
            'probing_historical_ips',
            sprintf('Probing %d historical IP address(es) for live related content.', count($ips)),
            DeploymentStatus::Processing
        );

        try {
            $probeResult = $this->dnsProbeService->probeMultiple(
                $deployment->domain,
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

            // 3. Third priority: score >= 8 or verdict 'Likely old site' AND not parking/sale
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
                    $deployment->logs()->create([
                        'step_name' => 'probing_historical_ips',
                        'status' => 'in_progress',
                        'message' => sprintf(
                            'No directly accessible working IP found. Testing %d Live (blocked) candidate IP(s) one by one via Cloudflare proxy...',
                            count($blockedCandidates)
                        ),
                    ]);

                    foreach ($blockedCandidates as $index => $candidate) {
                        $candidateIp = $candidate['ip'];

                        $deployment->refresh();
                        if (in_array($deployment->status, [DeploymentStatus::Cancelled, DeploymentStatus::Completed], true)) {
                            return null;
                        }

                        $deployment->logs()->create([
                            'step_name' => 'probing_historical_ips',
                            'status' => 'in_progress',
                            'message' => sprintf('Assigning Cloudflare DNS to blocked IP [%s] (%d/%d) and verifying live content...', $candidateIp, $index + 1, count($blockedCandidates)),
                        ]);

                        try {
                            $this->cloudflareDnsService->setupRunCloudDns($deployment->domain, $candidateIp, true);

                            sleep(3);

                            $verifyResult = $this->dnsProbeService->verifyLiveDomainContent($deployment->domain, 10);

                            if ($verifyResult['is_working'] && $verifyResult['is_content_related']) {
                                $deployment->logs()->create([
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
                                $deployment->logs()->create([
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
                            $deployment->logs()->create([
                                'step_name' => 'probing_historical_ips',
                                'status' => 'warning',
                                'message' => sprintf('Error verifying blocked IP [%s] via Cloudflare: %s', $candidateIp, $cfEx->getMessage()),
                            ]);
                        }
                    }
                }
            }

            if (! $matchingCandidate) {
                $deployment->logs()->create([
                    'step_name' => 'probing_historical_ips',
                    'status' => 'warning',
                    'message' => 'No working IP with content related to the domain was found. Falling back to RunCloud server deployment.',
                ]);

                return null;
            }

            $workingIp = $matchingCandidate['ip'];
            $title = $matchingCandidate['title'] ?? 'N/A';

            $deployment->logs()->create([
                'step_name' => 'probing_historical_ips',
                'status' => 'success',
                'message' => sprintf(
                    'Found working historical IP %s with related content (Title: "%s", Score: %s).',
                    $workingIp,
                    $title,
                    $matchingCandidate['score'] ?? 0
                ),
            ]);

            // Check if that working DNS/IP has a sitemap.xml
            $hasSitemap = $this->dnsProbeService->checkSitemapExists($deployment->domain, $workingIp);

            $deployment->logs()->create([
                'step_name' => 'probing_historical_ips',
                'status' => 'success',
                'message' => $hasSitemap
                    ? 'Sitemap detected on working IP (https://'.$deployment->domain.'/sitemap.xml). It will be submitted to Google Search Console.'
                    : 'No sitemap found on working IP. Sitemap submission to Google Search Console will be skipped.',
            ]);

            $detectedVersion = $matchingCandidate['detected_version'] ?? null;
            if (! empty($detectedVersion)) {
                $deployment->logs()->create([
                    'step_name' => 'probing_historical_ips',
                    'status' => 'success',
                    'message' => sprintf(
                        'Detected working live site version as [%s]. Cloudflare redirect Worker will be configured for %s.',
                        $detectedVersion,
                        $detectedVersion
                    ),
                ]);
            }

            return [
                'ip' => $workingIp,
                'has_sitemap' => $hasSitemap,
                'version' => $detectedVersion,
            ];
        } catch (Throwable $e) {
            Log::warning('Error probing historical IPs during deployment', [
                'deployment_id' => $deployment->id,
                'domain' => $deployment->domain,
                'error' => $e->getMessage(),
            ]);

            $deployment->logs()->create([
                'step_name' => 'probing_historical_ips',
                'status' => 'warning',
                'message' => 'Historical IP probing encountered an error ('.$e->getMessage().'). Falling back to RunCloud deployment.',
            ]);

            return null;
        }
    }

    private function failureMessage(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        if ($message === '') {
            return 'Archived website deployment failed due to an unexpected error.';
        }

        return 'Archived website deployment failed: '.$message;
    }

    private function registerWithSearchConsole(Deployment $deployment): void
    {
        $this->transition(
            $deployment,
            'registering_search_console',
            'Verifying the domain and submitting its sitemap to Google Search Console.',
        );

        try {
            $version = $deployment->version instanceof \BackedEnum
                ? $deployment->version->value
                : (string) $deployment->version;
            $sitemapHost = $version === 'www'
                ? 'www.'.$deployment->domain
                : $deployment->domain;

            $this->searchConsoleService->registerDomain(
                domain: $deployment->domain,
                sitemapUrl: "https://{$sitemapHost}/sitemap.xml",
                verifyOwnership: true,
                requestIndexing: true,
                submitSitemap: (bool) ($deployment->has_sitemap ?? true),
            );
            $deployment->logs()->create([
                'step_name' => 'registering_search_console',
                'status' => 'success',
                'message' => 'Google Search Console registration completed.',
            ]);
        } catch (Throwable $exception) {
            Log::warning('Google Search Console registration failed.', [
                'deployment_id' => $deployment->id,
                'domain' => $deployment->domain,
                'error' => $exception->getMessage(),
            ]);
            $deployment->logs()->create([
                'step_name' => 'registering_search_console',
                'status' => 'error',
                'message' => 'Google Search Console registration failed. The site was still deployed.',
            ]);
        }
    }

    private function transition(
        Deployment $deployment,
        string $step,
        string $message,
        ?DeploymentStatus $status = null,
    ): void {
        $attributes = ['current_step' => $step];

        if ($status !== null) {
            $attributes['status'] = $status;
        }

        $deployment->update($attributes);
        $deployment->logs()->create([
            'step_name' => $step,
            'status' => 'in_progress',
            'message' => $message,
        ]);
    }

    /** @param array<string, mixed> $webApp */
    private function deployArchiveToServer(
        Deployment $deployment,
        array $webApp,
        ?string $serverIp,
    ): void {
        if (blank($serverIp)) {
            throw new RuntimeException('The deployment server IP address is not configured.');
        }

        $webAppName = $webApp['web_app_name'] ?? null;

        if (! is_string($webAppName)
            || preg_match('/^[a-zA-Z0-9_-]+$/', $webAppName) !== 1) {
            throw new RuntimeException('RunCloud did not return a valid web application name.');
        }

        if (blank($deployment->archive_path)) {
            throw new RuntimeException('The archive ZIP path is not available.');
        }

        $this->transition(
            $deployment,
            'uploading_and_extracting_archive',
            'Uploading the ZIP by SFTP and extracting it into the web application.',
        );

        $webAppsRoot = rtrim((string) config('services.sftp.webapps_root', '/home/runcloud/webapps'), '/');
        $remoteAppRoot = $webAppsRoot.'/'.$webAppName;

        $result = $this->sftpArchiveService->uploadAndExtract(
            $serverIp,
            Storage::disk('local')->path($deployment->archive_path),
            $remoteAppRoot.'/archive-'.$deployment->id.'.zip',
            $remoteAppRoot.'/public_html',
            true,
            true,
        );

        if (($result['archive_uploaded'] ?? false) !== true
            || ($result['archive_deleted'] ?? false) !== true
            || (int) ($result['entries_extracted'] ?? 0) < 1) {
            throw new RuntimeException('The archive was not fully uploaded, extracted, and removed from the server.');
        }

        $deployment->logs()->create([
            'step_name' => 'uploading_and_extracting_archive',
            'status' => 'success',
            'message' => sprintf(
                'Uploaded and extracted %d archive entries; the remote ZIP was deleted.',
                $result['entries_extracted'],
            ),
        ]);

        $this->logDetectedSubdomainFolders($deployment, $result['subdomain_folders'] ?? []);
    }

    /** @param list<string> $subdomainFolders */
    private function logDetectedSubdomainFolders(Deployment $deployment, array $subdomainFolders): void
    {
        if ($subdomainFolders === []) {
            return;
        }

        $alreadyLogged = $deployment->logs()
            ->where('step_name', 'detecting_subdomains')
            ->exists();

        if ($alreadyLogged) {
            return;
        }

        foreach ($subdomainFolders as $folderName) {
            if (! is_string($folderName) || $folderName === '') {
                continue;
            }

            $deployment->logs()->create([
                'step_name' => 'detecting_subdomains',
                'status' => 'warning',
                'message' => $folderName,
            ]);
        }
    }
}
