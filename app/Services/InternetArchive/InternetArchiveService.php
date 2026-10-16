<?php

namespace App\Services\InternetArchive;

use App\Services\Http\ExternalHttpClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class InternetArchiveService
{
    public function __construct(
        private InternetArchiveSettings $settings,
        private ExternalHttpClient $httpClient,
    ) {}

    /**
     * Determine whether a user-supplied archive URL is safe to poll.
     */
    public function isAllowedArchiveUrl(string $archiveUrl): bool
    {
        $host = parse_url($archiveUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $allowedHosts = $this->settings->all()['allowed_hosts'];

        foreach ($allowedHosts as $allowedHost) {
            $allowedHost = strtolower(trim((string) $allowedHost));
            $host = strtolower($host);

            if ($allowedHost !== '' && ($host === $allowedHost || str_ends_with($host, '.'.$allowedHost))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether the generated archive is available as a ZIP download.
     */
    public function isArchiveReady(string $archiveUrl): bool
    {
        if (! $this->isAllowedArchiveUrl($archiveUrl)) {
            return false;
        }

        try {
            $response = $this->httpClient
                ->configure(Http::timeout($this->settings->all()['timeout']), 'internet_archive', 'check_archive_ready')
                ->accept('application/zip')
                ->head($archiveUrl);
        } catch (ConnectionException $exception) {
            Log::debug('Internet Archive readiness check could not connect.', [
                'host' => parse_url($archiveUrl, PHP_URL_HOST),
                'exception' => $exception::class,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        $contentType = strtolower(strtok($response->header('Content-Type') ?? '', ';') ?: '');
        $contentDisposition = strtolower($response->header('Content-Disposition') ?? '');

        return in_array($contentType, [
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream',
            'binary/octet-stream',
        ], true) || str_contains($contentDisposition, '.zip');
    }

    /**
     * Verify connectivity to an allowed Internet Archive host.
     */
    public function testConnection(string $url): bool
    {
        if (! $this->isAllowedArchiveUrl($url)) {
            return false;
        }

        try {
            $response = $this->httpClient
                ->configure(Http::timeout($this->settings->all()['timeout']), 'internet_archive', 'test_connection')
                ->head($url);
        } catch (ConnectionException $exception) {
            Log::warning('Internet Archive connection test could not connect.', [
                'host' => parse_url($url, PHP_URL_HOST),
                'exception' => $exception::class,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        return $response->status() < 500;
    }

    /**
     * Retrieve available snapshot versions for a domain.
     */
    public function getAvailableSnapshots(string $domain): array
    {
        // Integration logic for Internet Archive / Wayback Machine
        return [];
    }

    /**
     * Download or extract snapshot files.
     */
    public function downloadSnapshot(string $domain, string $version, string $destinationPath): bool
    {
        Log::warning('Snapshot download is not implemented.', [
            'domain' => $domain,
            'version' => $version,
        ]);

        return false;
    }

    /**
     * Download an archive ZIP directly from an archive URL to a local destination.
     */
    public function downloadArchiveFromUrl(string $archiveUrl, string $destinationZipPath): bool
    {
        if (! $this->isAllowedArchiveUrl($archiveUrl)) {
            Log::warning('Attempted to download from unallowed archive URL.', ['url' => $archiveUrl]);

            return false;
        }

        $directory = dirname($destinationZipPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $partialPath = $destinationZipPath.'.part';
        if (file_exists($partialPath)) {
            @unlink($partialPath);
        }

        try {
            $response = $this->httpClient
                ->configure(
                    Http::connectTimeout($this->settings->all()['timeout'])
                        ->timeout((int) config('services.internet_archive.download_timeout', 1800)),
                    'internet_archive',
                    'download_archive',
                )
                ->sink($partialPath)
                ->get($archiveUrl);

            if (! $response->successful() || ! is_file($partialPath) || filesize($partialPath) === 0) {
                Log::warning('Failed to download archive from URL.', [
                    'url' => $archiveUrl,
                    'status' => $response->status(),
                ]);

                @unlink($partialPath);

                return false;
            }

            if (file_exists($destinationZipPath)) {
                @unlink($destinationZipPath);
            }

            if (! rename($partialPath, $destinationZipPath)) {
                @unlink($partialPath);

                throw new RuntimeException('The downloaded archive could not be finalized.');
            }

            return true;
        } catch (Throwable $e) {
            Log::error('Exception while downloading archive from URL.', [
                'url' => $archiveUrl,
                'error' => $e->getMessage(),
            ]);

            @unlink($partialPath);

            return false;
        }
    }
}
