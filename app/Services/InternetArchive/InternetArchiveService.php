<?php

namespace App\Services\InternetArchive;

use App\Services\Http\ExternalHttpClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        // Retrieval and extraction logic
        return true;
    }
}
