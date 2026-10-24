<?php

namespace App\Jobs;

use App\Models\Deployment;
use App\Services\RunCloud\RunCloudWebAppService;
use App\Services\Sftp\SftpFileService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class PrepareDeploymentZip implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(
        public Deployment $deployment,
        public string $downloadToken
    ) {
        $this->onQueue('zip-downloads');
    }

    public function handle(SftpFileService $sftpFileService, RunCloudWebAppService $runCloudWebAppService): void
    {
        $cacheKey = "deployment_zip:{$this->downloadToken}";
        $this->deployment->load('server');

        $server = $this->deployment->server;
        $host = $server?->ip_address;

        if (blank($host)) {
            Cache::put($cacheKey, [
                'status' => 'failed',
                'error' => 'The deployment server IP address is not configured.',
            ], now()->addMinutes(30));
            return;
        }

        $webAppName = $runCloudWebAppService->generateWebAppName($this->deployment->domain);
        $webAppsRoot = rtrim((string) config('services.sftp.webapps_root', '/home/runcloud/webapps'), '/');
        $rootDir = "{$webAppsRoot}/{$webAppName}/public_html";

        Cache::put($cacheKey, [
            'status' => 'processing',
            'message' => 'Creating archive on remote server...',
        ], now()->addMinutes(30));

        try {
            $zipBasename = str_replace(['.', ' ', '/'], ['-', '-', '-'], $this->deployment->domain);
            $remoteArchive = $sftpFileService->createRemoteArchive($host, $rootDir, $zipBasename);

            $domainClean = preg_replace('/[^a-zA-Z0-9\.\-_]/', '', $this->deployment->domain);
            $isTar = ($remoteArchive['format'] ?? 'zip') === 'tar.gz';
            $downloadFilename = $isTar ? "{$domainClean}.tar.gz" : "{$domainClean}.zip";
            $contentType = $isTar ? 'application/gzip' : 'application/zip';
            $localDiskPath = "archives/{$this->downloadToken}" . ($isTar ? '.tar.gz' : '.zip');
            $fullLocalPath = storage_path("app/{$localDiskPath}");

            if (! is_dir(dirname($fullLocalPath))) {
                @mkdir(dirname($fullLocalPath), 0755, true);
            }

            // Transfer the file from remote server to local storage inside this queue worker
            $sftp = app(\App\Services\Sftp\SftpConnectionFactory::class)->connect($host);
            $sftp->get($remoteArchive['path'], $fullLocalPath);

            // Clean up remote archive on the server
            try {
                $sftp->delete($remoteArchive['path']);
            } catch (Throwable) {
                // Ignore cleanup error
            }

            Cache::put($cacheKey, [
                'status' => 'ready',
                'local_path' => $fullLocalPath,
                'size' => file_exists($fullLocalPath) ? (int) filesize($fullLocalPath) : (int) $remoteArchive['size'],
                'format' => $remoteArchive['format'],
                'filename' => $downloadFilename,
                'content_type' => $contentType,
            ], now()->addMinutes(30));
        } catch (Throwable $e) {
            Log::error('PrepareDeploymentZip failed: ' . $e->getMessage(), [
                'deployment_id' => $this->deployment->id,
                'domain' => $this->deployment->domain,
            ]);

            Cache::put($cacheKey, [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ], now()->addMinutes(30));
        }
    }

    public function failed(?Throwable $exception): void
    {
        $cacheKey = "deployment_zip:{$this->downloadToken}";
        Cache::put($cacheKey, [
            'status' => 'failed',
            'error' => $exception?->getMessage() ?? 'Archive preparation failed unexpectedly.',
        ], now()->addMinutes(30));
    }
}
