<?php

namespace App\Services\Deployment;

use App\Enums\DeploymentStatus;
use App\Models\Deployment;
use App\Services\ApiUsage\ApiUsageContext;
use App\Services\Cloudflare\CloudflareWorkerService;
use App\Services\GoogleSearchConsole\GoogleSearchConsoleService;
use App\Services\InternetArchive\InternetArchiveService;
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
        ?ApiUsageContext $apiUsageContext = null,
    ) {
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
            $version = $deployment->version instanceof \BackedEnum
                ? $deployment->version->value
                : (string) $deployment->version;

            if ($deployment->archive_source === 'upload') {
                $this->transition($deployment, 'preparing_archive', 'Preparing the uploaded ZIP.', DeploymentStatus::Processing);

                if (blank($deployment->archive_path)
                    || ! Storage::disk('local')->exists($deployment->archive_path)) {
                    throw new RuntimeException('The uploaded ZIP is no longer available.');
                }
            } else {
                $this->transition($deployment, 'downloading_archive', 'Downloading the archived website.', DeploymentStatus::Processing);

                $archiveRelativePath = "deployments/{$deployment->id}/archive.zip";
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

            if (! empty($result['already_exists'])) {
                $canResumeDeployment = $deployment->logs()
                    ->where('step_name', 'uploading_and_extracting_archive')
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

            $this->deployArchiveToServer($deployment, $result, $serverIp);

            $deployment->refresh();
            if ($deployment->status === DeploymentStatus::Cancelled) {
                return $deployment;
            }

            $this->transition(
                $deployment,
                'configuring_cloudflare_workers',
                'Assigning the Cloudflare Worker routes.',
            );

            $this->cloudflareWorkerService->changeWorkers($deployment->domain, $version);

            $deployment->refresh();
            if ($deployment->status === DeploymentStatus::Cancelled) {
                return $deployment;
            }

            if (config('services.google_search_console.enabled')) {
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
                    'message' => 'Archived website deployment failed. Review the server logs for details.',
                ]);
            }

            throw $exception;
        }
    }

    private function registerWithSearchConsole(Deployment $deployment): void
    {
        $this->transition(
            $deployment,
            'registering_search_console',
            'Registering the domain, sitemap, and indexing request with Google Search Console.',
        );

        try {
            $this->searchConsoleService->registerDomain($deployment->domain);
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
    }
}
