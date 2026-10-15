<?php

namespace App\Services\Sftp;

use phpseclib3\Net\SFTP;
use RuntimeException;

class SftpArchiveService
{
    public function __construct(
        private SftpConnectionFactory $connectionFactory,
    ) {}

    /** @return array{archive_path: string, destination_path: string, entries_extracted: int, archive_deleted: bool, archive_uploaded: bool, deleted_index_files: list<string>} */
    public function extract(
        string $host,
        string $archivePath,
        string $destinationPath,
        bool $deleteArchive = false,
        bool $deleteExistingIndex = true,
    ): array {
        $this->guardRemotePath($archivePath);
        $this->guardRemotePath($destinationPath);

        $client = $this->connectionFactory->connect($host);

        return $this->extractWithClient(
            $client,
            $archivePath,
            $destinationPath,
            $deleteArchive,
            $deleteExistingIndex,
            false,
        );
    }

    /** @return array{archive_path: string, destination_path: string, entries_extracted: int, archive_deleted: bool, archive_uploaded: bool, deleted_index_files: list<string>} */
    public function uploadAndExtract(
        string $host,
        string $localArchivePath,
        string $remoteArchivePath,
        string $destinationPath,
        bool $deleteRemoteArchive = true,
        bool $deleteExistingIndex = true,
    ): array {
        if (! is_file($localArchivePath) || ! is_readable($localArchivePath)) {
            throw new RuntimeException('The local ZIP archive could not be read.');
        }

        $this->guardRemotePath($remoteArchivePath);
        $this->guardRemotePath($destinationPath);

        $client = $this->connectionFactory->connect($host);
        $remoteArchiveDirectory = dirname($remoteArchivePath);

        if (! $client->is_dir($remoteArchiveDirectory)
            && ! $client->mkdir($remoteArchiveDirectory, -1, true)) {
            throw new RuntimeException('The remote archive directory could not be created.');
        }

        if (! $client->put($remoteArchivePath, $localArchivePath, SFTP::SOURCE_LOCAL_FILE)) {
            throw new RuntimeException('The ZIP archive could not be uploaded by SFTP.');
        }

        return $this->extractWithClient(
            $client,
            $remoteArchivePath,
            $destinationPath,
            $deleteRemoteArchive,
            $deleteExistingIndex,
            true,
        );
    }

    /** @return array{archive_path: string, destination_path: string, entries_extracted: int, archive_deleted: bool, archive_uploaded: bool, deleted_index_files: list<string>} */
    private function extractWithClient(
        SFTP $client,
        string $archivePath,
        string $destinationPath,
        bool $deleteArchive,
        bool $deleteExistingIndex,
        bool $archiveUploaded,
    ): array {
        if (! $client->is_file($archivePath)) {
            throw new RuntimeException('The remote ZIP archive was not found.');
        }

        if (! $client->is_dir($destinationPath)
            && ! $client->mkdir($destinationPath, -1, true)) {
            throw new RuntimeException('The remote destination directory could not be created.');
        }

        $entries = $this->listEntries($client, $archivePath);
        $this->guardArchiveEntries($entries);
        $deletedIndexFiles = $deleteExistingIndex
            ? $this->deleteExistingIndexFiles($client, $destinationPath)
            : [];

        $command = sprintf(
            'unzip -oq %s -d %s',
            $this->shellArgument($archivePath),
            $this->shellArgument($destinationPath),
        );

        $client->exec($command);

        if ($client->getExitStatus() !== 0) {
            throw new RuntimeException('The remote ZIP archive could not be extracted.');
        }

        if ($deleteArchive && ! $client->delete($archivePath)) {
            throw new RuntimeException('The ZIP was extracted, but the remote archive could not be deleted.');
        }

        return [
            'archive_path' => $archivePath,
            'destination_path' => $destinationPath,
            'entries_extracted' => count($entries),
            'archive_deleted' => $deleteArchive,
            'archive_uploaded' => $archiveUploaded,
            'deleted_index_files' => $deletedIndexFiles,
        ];
    }

    /** @return list<string> */
    private function deleteExistingIndexFiles(SFTP $client, string $destinationPath): array
    {
        $indexFiles = (array) config('services.sftp.default_index_files', ['index.html', 'index.htm']);
        $deletedFiles = [];

        foreach ($indexFiles as $indexFile) {
            if (! is_string($indexFile) || preg_match('/^[a-zA-Z0-9._-]+$/', $indexFile) !== 1) {
                continue;
            }

            $path = rtrim($destinationPath, '/').'/'.$indexFile;

            if (! $client->is_file($path)) {
                continue;
            }

            if (! $client->delete($path)) {
                throw new RuntimeException("The existing {$indexFile} file could not be deleted.");
            }

            $deletedFiles[] = $path;
        }

        return $deletedFiles;
    }

    /** @return list<string> */
    private function listEntries(SFTP $client, string $archivePath): array
    {
        $output = $client->exec('unzip -Z1 '.$this->shellArgument($archivePath));

        if ($client->getExitStatus() !== 0 || ! is_string($output)) {
            throw new RuntimeException('The remote file is not a readable ZIP archive.');
        }

        $entries = array_values(array_filter(
            preg_split('/\r\n|\r|\n/', $output) ?: [],
            static fn (string $entry): bool => $entry !== '',
        ));

        if ($entries === []) {
            throw new RuntimeException('The remote ZIP archive is empty.');
        }

        $maximumEntries = max(1, (int) config('services.sftp.max_archive_entries', 10000));

        if (count($entries) > $maximumEntries) {
            throw new RuntimeException('The remote ZIP archive contains too many entries.');
        }

        return $entries;
    }

    /** @param list<string> $entries */
    private function guardArchiveEntries(array $entries): void
    {
        foreach ($entries as $entry) {
            $normalized = str_replace('\\', '/', $entry);
            $segments = explode('/', $normalized);

            if (str_starts_with($normalized, '/')
                || preg_match('/^[a-zA-Z]:\//', $normalized) === 1
                || in_array('..', $segments, true)
                || str_contains($entry, "\0")) {
                throw new RuntimeException('The ZIP contains an unsafe file path.');
            }
        }
    }

    private function shellArgument(string $value): string
    {
        return "'".str_replace("'", "'\"'\"'", $value)."'";
    }

    private function guardRemotePath(string $path): void
    {
        $segments = explode('/', str_replace('\\', '/', $path));

        if (! str_starts_with($path, '/')
            || in_array('..', $segments, true)
            || str_contains($path, "\0")
            || str_contains($path, "\r")
            || str_contains($path, "\n")) {
            throw new RuntimeException('The remote path is invalid.');
        }

        $allowedRoots = array_values(array_filter(array_map(
            static fn (string $root): string => rtrim(trim($root), '/'),
            (array) config('services.sftp.allowed_roots', ['/home/runcloud']),
        )));

        foreach ($allowedRoots as $root) {
            if ($path === $root || str_starts_with($path, $root.'/')) {
                return;
            }
        }

        throw new RuntimeException('The remote path is outside the configured SFTP roots.');
    }
}
