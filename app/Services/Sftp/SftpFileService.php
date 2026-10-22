<?php

namespace App\Services\Sftp;

use phpseclib3\Net\SFTP;
use RuntimeException;

class SftpFileService
{
    public function __construct(
        private SftpConnectionFactory $connectionFactory,
    ) {}

    /**
     * List files and folders in a remote directory.
     *
     * @return array<int, array{name: string, path: string, type: string, size: int, mtime: int}>
     */
    public function listDirectory(string $host, string $remoteDir): array
    {
        $this->guardPath($remoteDir);

        $client = $this->connectionFactory->connect($host);

        if (! $client->is_dir($remoteDir)) {
            throw new RuntimeException("Directory not found: {$remoteDir}");
        }

        $rawList = $client->rawlist($remoteDir);
        if ($rawList === false) {
            throw new RuntimeException("Could not read directory: {$remoteDir}");
        }

        $items = [];
        foreach ($rawList as $filename => $info) {
            if ($filename === '.' || $filename === '..') {
                continue;
            }

            $fullPath = rtrim($remoteDir, '/') . '/' . $filename;
            $isDir = isset($info['type']) && $info['type'] === NET_SFTP_TYPE_DIRECTORY;

            $items[] = [
                'name' => (string) $filename,
                'path' => $fullPath,
                'type' => $isDir ? 'directory' : 'file',
                'size' => (int) ($info['size'] ?? 0),
                'mtime' => (int) ($info['mtime'] ?? 0),
            ];
        }

        // Sort: directories first, then alphabetically
        usort($items, function ($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return $items;
    }

    /**
     * Read the contents of a remote file.
     */
    public function readFile(string $host, string $filePath): string
    {
        $this->guardPath($filePath);

        $client = $this->connectionFactory->connect($host);

        if (! $client->is_file($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        // 10MB safety limit for in-browser editing
        $size = $client->filesize($filePath);
        if ($size !== false && $size > 10 * 1024 * 1024) {
            throw new RuntimeException("File is too large to open in the editor (max 10MB).");
        }

        $content = $client->get($filePath);
        if ($content === false) {
            throw new RuntimeException("Could not read file: {$filePath}");
        }

        return $content;
    }

    /**
     * Write contents to a remote file.
     */
    public function writeFile(string $host, string $filePath, string $content): void
    {
        $this->guardPath($filePath);

        $client = $this->connectionFactory->connect($host);

        $dir = dirname($filePath);
        if (! $client->is_dir($dir) && ! $client->mkdir($dir, -1, true)) {
            throw new RuntimeException("Could not create parent directory: {$dir}");
        }

        if (! $client->put($filePath, $content)) {
            throw new RuntimeException("Failed to save file: {$filePath}");
        }
    }

    /**
     * Prevent path traversal outside allowed root paths.
     */
    private function guardPath(string $path): void
    {
        $normalized = str_replace('\\', '/', $path);

        if (str_contains($normalized, '..')) {
            throw new RuntimeException('Path traversal is not permitted.');
        }

        /** @var list<string> $allowedRoots */
        $allowedRoots = config('services.sftp.allowed_roots', ['/home/runcloud']);

        $allowed = false;
        foreach ($allowedRoots as $root) {
            $root = rtrim(str_replace('\\', '/', $root), '/');
            if (str_starts_with($normalized, $root)) {
                $allowed = true;
                break;
            }
        }

        if (! $allowed) {
            throw new RuntimeException("Remote path [{$path}] is outside allowed directories.");
        }
    }
}
