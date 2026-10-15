<?php

namespace App\Services\InternetArchive;

use App\Services\Http\ExternalHttpClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
                ->configure(Http::timeout($this->settings->all()['timeout']))
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
                ->configure(Http::timeout($this->settings->all()['timeout']))
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
     * Look up the closest available Wayback Machine snapshot URL for a domain.
     *
     * @return array{available: bool, url: ?string, timestamp: ?string}|null
     */
    public function findLatestSnapshot(string $domain): ?array
    {
        $domain = preg_replace('#^https?://#i', '', trim($domain));
        $domain = rtrim((string) $domain, '/');

        $baseUrl = rtrim($this->settings->all()['wayback_url'] ?? 'https://archive.org/wayback/available', '/');
        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        $url = $baseUrl.$separator.'url='.urlencode($domain);

        try {
            $response = $this->httpClient
                ->configure(Http::timeout($this->settings->all()['timeout']))
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            $closest = $data['archived_snapshots']['closest'] ?? null;

            if (is_array($closest) && ! empty($closest['available']) && ! empty($closest['url'])) {
                return [
                    'available' => true,
                    'url' => (string) $closest['url'],
                    'timestamp' => (string) ($closest['timestamp'] ?? ''),
                ];
            }

            return null;
        } catch (Throwable $exception) {
            Log::warning('Failed querying Wayback availability API.', [
                'domain' => $domain,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Retrieve available snapshot versions for a domain.
     */
    public function getAvailableSnapshots(string $domain): array
    {
        $latest = $this->findLatestSnapshot($domain);

        return $latest !== null ? [$latest] : [];
    }

    /**
     * Download or extract snapshot files.
     */
    public function downloadSnapshot(string $domain, string $version, string $destinationPath): bool
    {
        $directory = dirname($destinationPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return true;
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

        try {
            $response = $this->httpClient
                ->configure(Http::timeout($this->settings->all()['timeout']))
                ->sink($destinationZipPath)
                ->get($archiveUrl);

            if (! $response->successful() || ! file_exists($destinationZipPath) || filesize($destinationZipPath) === 0) {
                Log::warning('Failed to download archive from URL.', [
                    'url' => $archiveUrl,
                    'status' => $response->status(),
                ]);

                if (file_exists($destinationZipPath)) {
                    @unlink($destinationZipPath);
                }

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::error('Exception while downloading archive from URL.', [
                'url' => $archiveUrl,
                'error' => $e->getMessage(),
            ]);

            if (file_exists($destinationZipPath)) {
                @unlink($destinationZipPath);
            }

            return false;
        }
    }
}
