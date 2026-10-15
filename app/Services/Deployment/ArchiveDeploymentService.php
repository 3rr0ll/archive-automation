<?php

namespace App\Services\Deployment;

use App\Enums\DeploymentStatus;
use App\Models\Deployment;
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
    public function __construct(
        private InternetArchiveService $archiveService,
        private RunCloudService $runCloudService,
        private CloudflareWorkerService $cloudflareWorkerService,
        private GoogleSearchConsoleService $searchConsoleService,
        private SftpArchiveService $sftpArchiveService,
    ) {}

    public function deploy(Deployment $deployment): Deployment
    {
        $deployment->refresh();

        if (in_array($deployment->status, [DeploymentStatus::Cancelled, DeploymentStatus::Completed], true)) {
            return $deployment;
        }

        try {
            $destinationPath = storage_path('app/private/deployments/'.$deployment->id);
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

                if (! $this->archiveService->downloadSnapshot($deployment->domain, $version, $destinationPath)) {
                    throw new RuntimeException('The archived website could not be downloaded.');
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

            if (! empty($result['already_exists'])) {
                $canResumeUploadedDeployment = $deployment->archive_source === 'upload'
                    && $deployment->logs()
                        ->where('step_name', 'uploading_and_extracting_archive')
                        ->exists();

                if (! $canResumeUploadedDeployment) {
                    throw new RuntimeException('A RunCloud web application already exists for this domain.');
                }

                $result['web_app_name'] = $this->runCloudService->generateWebAppName($deployment->domain);
                $deployment->logs()->create([
                    'step_name' => 'provisioning_web_app',
                    'status' => 'success',
                    'message' => 'Reusing the RunCloud web application created by an earlier deployment attempt.',
                ]);
            }

            $deployment->refresh();
            if ($deployment->status === DeploymentStatus::Cancelled) {
                return $deployment;
            }

            if ($deployment->archive_source === 'upload') {
                $this->deployUploadedArchive($deployment, $result, $serverIp);

                $deployment->refresh();
                if ($deployment->status === DeploymentStatus::Cancelled) {
                    return $deployment;
                }
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
    private function deployUploadedArchive(
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
            throw new RuntimeException('The uploaded ZIP path is not available.');
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
