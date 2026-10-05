<?php

namespace App\Services\InternetArchive;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class InternetArchiveService
{
    /**
     * Determine whether a user-supplied archive URL is safe to poll.
     */
    public function isAllowedArchiveUrl(string $archiveUrl): bool
    {
        $host = parse_url($archiveUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $allowedHosts = Config::get('services.internet_archive.allowed_archive_hosts', []);

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
            $response = Http::timeout((int) Config::get('services.internet_archive.timeout', 20))
                ->accept('application/zip')
                ->head($archiveUrl);
        } catch (ConnectionException) {
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
