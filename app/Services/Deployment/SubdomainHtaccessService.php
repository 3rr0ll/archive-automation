<?php

namespace App\Services\Deployment;

use App\Services\Sftp\SftpFileService;
use RuntimeException;
use Throwable;

class SubdomainHtaccessService
{
    public const BEGIN_MARKER = '# BEGIN Subdomain Routing';

    public const END_MARKER = '# END Subdomain Routing';

    public const LEGACY_BEGIN_MARKER = '# BEGIN Archive Automation subdomain routing';

    public const LEGACY_END_MARKER = '# END Archive Automation subdomain routing';

    public function __construct(
        private SftpFileService $sftpFileService,
    ) {}

    /**
     * Inspect remote .htaccess file to determine existence, closing </IfModule>, and currently configured subdomains.
     *
     * @return array{
     *     exists: bool,
     *     has_if_module: bool,
     *     configured_subdomains: list<string>,
     * }
     */
    public function inspect(string $host, string $htaccessPath): array
    {
        if (blank($host)) {
            throw new RuntimeException('The deployment server IP address is not configured.');
        }

        if (! $this->sftpFileService->fileExists($host, $htaccessPath)) {
            return [
                'exists' => false,
                'has_if_module' => false,
                'configured_subdomains' => [],
            ];
        }

        $content = $this->sftpFileService->readFile($host, $htaccessPath);
        $hasIfModule = (bool) preg_match('/<\/IfModule>/i', $content);
        $configuredSubdomains = $this->extractConfiguredSubdomains($content);

        return [
            'exists' => true,
            'has_if_module' => $hasIfModule,
            'configured_subdomains' => $configuredSubdomains,
        ];
    }

    /**
     * Preview the generated rules, diff (added, updated, removed), and notices without writing to disk.
     *
     * @param  list<string>  $subdomainFolders
     * @return array{
     *     rules: string,
     *     added: list<string>,
     *     updated: list<string>,
     *     removed: list<string>,
     *     has_if_module: bool,
     *     notice: ?string,
     *     warnings: list<string>,
     *     configured_subdomains: list<string>,
     * }
     */
    public function preview(
        string $domain,
        array $subdomainFolders,
        ?string $existingContent = null,
    ): array {
        $sanitized = $this->sanitizeFolders($subdomainFolders);
        $subdomains = $sanitized['subdomains'];

        $previousSubdomains = $existingContent !== null
            ? $this->extractConfiguredSubdomains($existingContent)
            : [];

        $added = array_values(array_diff($subdomains, $previousSubdomains));
        $removed = array_values(array_diff($previousSubdomains, $subdomains));
        $updated = array_values(array_intersect($subdomains, $previousSubdomains));

        $eol = ($existingContent !== null && str_contains($existingContent, "\r\n")) ? "\r\n" : "\n";
        $rules = $this->buildRoutingBlock($domain, $subdomains, $eol);

        $hasIfModule = $existingContent !== null
            ? (bool) preg_match('/<\/IfModule>/i', $existingContent)
            : true;

        $notice = ! $hasIfModule
            ? 'No </IfModule> tag found in .htaccess; rules will be appended to the end of the file.'
            : null;

        return [
            'rules' => $rules,
            'added' => $added,
            'updated' => $updated,
            'removed' => $removed,
            'has_if_module' => $hasIfModule,
            'notice' => $notice,
            'warnings' => $sanitized['warnings'],
            'configured_subdomains' => $subdomains,
        ];
    }

    /**
     * Apply rules to .htaccess on the remote server via SFTP.
     *
     * @param  list<string>  $subdomainFolders
     * @return array{
     *     success: bool,
     *     message: string,
     *     backup_file: string,
     *     backup_path: string,
     *     added: list<string>,
     *     updated: list<string>,
     *     removed: list<string>,
     *     notice: ?string,
     *     configured_subdomains: list<string>,
     *     warnings: list<string>,
     * }
     */
    public function apply(
        string $host,
        string $htaccessPath,
        string $domain,
        array $subdomainFolders,
    ): array {
        if (blank($host)) {
            throw new RuntimeException('The deployment server IP address is not configured.');
        }

        if (! $this->sftpFileService->fileExists($host, $htaccessPath)) {
            throw new RuntimeException("The .htaccess file does not exist on the server at {$htaccessPath}.");
        }

        try {
            $existingContent = $this->sftpFileService->readFile($host, $htaccessPath);
        } catch (Throwable $e) {
            throw new RuntimeException("The .htaccess file at {$htaccessPath} is not readable: {$e->getMessage()}", 0, $e);
        }

        $previousSubdomains = $this->extractConfiguredSubdomains($existingContent);
        $sanitized = $this->sanitizeFolders($subdomainFolders);
        $newSubdomains = $sanitized['subdomains'];

        $added = array_values(array_diff($newSubdomains, $previousSubdomains));
        $removed = array_values(array_diff($previousSubdomains, $newSubdomains));
        $updated = array_values(array_intersect($newSubdomains, $previousSubdomains));

        $injection = $this->injectRules($existingContent, $domain, $subdomainFolders);
        $updatedContent = $injection['content'];

        $dir = dirname($htaccessPath);
        $timestamp = gmdate('Ymd-His');
        $backupFilename = ".htaccess.bak-{$timestamp}";
        $backupPath = "{$dir}/{$backupFilename}";

        $counter = 1;
        while ($counter <= 100 && $this->sftpFileService->fileExists($host, $backupPath)) {
            $backupFilename = ".htaccess.bak-{$timestamp}-{$counter}";
            $backupPath = "{$dir}/{$backupFilename}";
            $counter++;
        }

        $permissions = $this->sftpFileService->getFilePermissions($host, $htaccessPath);

        try {
            $this->sftpFileService->writeFile($host, $backupPath, $existingContent, $permissions);
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to create backup at {$backupPath}: {$e->getMessage()}", 0, $e);
        }

        try {
            $this->sftpFileService->writeFile($host, $htaccessPath, $updatedContent, $permissions);
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to write updated .htaccess at {$htaccessPath}: {$e->getMessage()}", 0, $e);
        }

        $messageParts = [];
        if ($added !== []) {
            $messageParts[] = 'Added: '.implode(', ', $added);
        }
        if ($updated !== []) {
            $messageParts[] = 'Updated: '.implode(', ', $updated);
        }
        if ($removed !== []) {
            $messageParts[] = 'Removed: '.implode(', ', $removed);
        }

        $changesSummary = $messageParts === []
            ? 'No subdomain rule changes'
            : implode('; ', $messageParts);

        $message = "Subdomain routing updated in .htaccess ({$changesSummary}). Backup created: {$backupFilename}.";

        return [
            'success' => true,
            'message' => $message,
            'backup_file' => $backupFilename,
            'backup_path' => $backupPath,
            'added' => $added,
            'updated' => $updated,
            'removed' => $removed,
            'notice' => $injection['notice'],
            'configured_subdomains' => $newSubdomains,
            'warnings' => $sanitized['warnings'],
        ];
    }

    /**
     * Inject or update generated subdomain rules in .htaccess content.
     *
     * @param  list<string>  $subdomainFolders
     * @return array{
     *     content: string,
     *     has_if_module: bool,
     *     notice: ?string,
     *     warnings: list<string>,
     * }
     */
    public function injectRules(string $htaccessContent, string $domain, array $subdomainFolders): array
    {
        $eol = str_contains($htaccessContent, "\r\n") ? "\r\n" : "\n";
        $sanitized = $this->sanitizeFolders($subdomainFolders);
        $subdomains = $sanitized['subdomains'];

        $contentWithoutBlock = $this->removeManagedBlock($htaccessContent);
        $block = $this->buildRoutingBlock($domain, $subdomains, $eol);

        if ($block === '') {
            $cleanContent = trim($contentWithoutBlock);

            return [
                'content' => $cleanContent === '' ? '' : $cleanContent.$eol,
                'has_if_module' => (bool) preg_match('/<\/IfModule>/i', $contentWithoutBlock),
                'notice' => null,
                'warnings' => $sanitized['warnings'],
            ];
        }

        if (preg_match('/<\/IfModule>/i', $contentWithoutBlock, $matches, PREG_OFFSET_CAPTURE)) {
            $match = $matches[0][0];
            $offset = (int) $matches[0][1];
            $insertAt = $offset + strlen($match);

            $before = rtrim(substr($contentWithoutBlock, 0, $insertAt));
            $after = ltrim(substr($contentWithoutBlock, $insertAt), "\r\n");

            $newContent = $before.$eol.$eol.$block;
            if ($after !== '') {
                $newContent .= $eol.$eol.$after;
            } else {
                $newContent .= $eol;
            }

            return [
                'content' => $newContent,
                'has_if_module' => true,
                'notice' => null,
                'warnings' => $sanitized['warnings'],
            ];
        }

        $trimmed = rtrim($contentWithoutBlock);
        $newContent = ($trimmed === '' ? '' : $trimmed.$eol.$eol).$block.$eol;

        return [
            'content' => $newContent,
            'has_if_module' => false,
            'notice' => 'No </IfModule> tag found in .htaccess; rules were appended to the end of the file.',
            'warnings' => $sanitized['warnings'],
        ];
    }

    /**
     * Build the Apache rewrite rules block for the given subdomains.
     *
     * @param  list<string>  $subdomains
     */
    public function buildRoutingBlock(string $domain, array $subdomains, string $eol = "\n"): string
    {
        if ($subdomains === []) {
            return '';
        }

        $escapedDomain = $this->escapeDomainForRegex($domain);
        $rules = [];

        foreach ($subdomains as $subdomain) {
            $folderName = $subdomain.'-sub';
            $rules[] = implode($eol, [
                "# Route subdomain to folder: {$subdomain}",
                "RewriteCond %{HTTP_HOST} ^{$subdomain}\.{$escapedDomain}$ [NC]",
                "RewriteCond %{REQUEST_URI} !^/{$folderName}(/|$)",
                "RewriteRule ^(.*)$ /{$folderName}/$1 [END]",
            ]);
        }

        return self::BEGIN_MARKER.$eol.implode($eol.$eol, $rules).$eol.self::END_MARKER;
    }

    /**
     * Extract configured subdomains from an .htaccess file.
     *
     * @return list<string>
     */
    public function extractConfiguredSubdomains(string $htaccessContent): array
    {
        $pattern = '/(?:'.preg_quote(self::BEGIN_MARKER, '/').'|'.preg_quote(self::LEGACY_BEGIN_MARKER, '/').')(.*?)(?:'.preg_quote(self::END_MARKER, '/').'|'.preg_quote(self::LEGACY_END_MARKER, '/').')/s';

        $blockContent = '';
        if (preg_match($pattern, $htaccessContent, $matches)) {
            $blockContent = $matches[1];
        }

        if ($blockContent === '') {
            return [];
        }

        $subdomains = [];
        if (preg_match_all('/# Route subdomain to folder:\s*([a-zA-Z0-9_-]+)/i', $blockContent, $matches)) {
            foreach ($matches[1] as $subdomain) {
                $clean = strtolower(trim($subdomain));
                if (str_ends_with($clean, '-sub')) {
                    $clean = substr($clean, 0, -4);
                }
                if ($clean !== '' && preg_match('/^[a-z0-9-]+$/', $clean)) {
                    $subdomains[$clean] = true;
                }
            }
        }

        if ($subdomains === [] && preg_match_all('/RewriteCond\s+%{HTTP_HOST}\s+\^([a-zA-Z0-9_-]+)\\\\?\./i', $blockContent, $matches)) {
            foreach ($matches[1] as $subdomain) {
                $clean = strtolower(trim($subdomain));
                if (str_ends_with($clean, '-sub')) {
                    $clean = substr($clean, 0, -4);
                }
                if ($clean !== '' && preg_match('/^[a-z0-9-]+$/', $clean)) {
                    $subdomains[$clean] = true;
                }
            }
        }

        $result = array_keys($subdomains);
        natcasesort($result);

        return array_values($result);
    }

    /**
     * Strip managed block (both current and legacy markers) from .htaccess content.
     */
    public function removeManagedBlock(string $content): string
    {
        $patterns = [
            '/\r?\n?'.preg_quote(self::BEGIN_MARKER, '/').'.*?'.preg_quote(self::END_MARKER, '/').'\r?\n?/s',
            '/\r?\n?'.preg_quote(self::LEGACY_BEGIN_MARKER, '/').'.*?'.preg_quote(self::LEGACY_END_MARKER, '/').'\r?\n?/s',
        ];

        $cleaned = $content;
        foreach ($patterns as $pattern) {
            $cleaned = (string) preg_replace($pattern, "\n", $cleaned);
        }

        return $cleaned;
    }

    /**
     * Sanitize folder names and map them to base subdomain names.
     *
     * @param  list<string>  $folders
     * @return array{subdomains: list<string>, warnings: list<string>}
     */
    public function sanitizeFolders(array $folders): array
    {
        $valid = [];
        $warnings = [];

        foreach ($folders as $rawFolder) {
            if (! is_string($rawFolder)) {
                continue;
            }

            $trimmed = trim($rawFolder);
            if ($trimmed === '') {
                continue;
            }

            $folder = strtolower($trimmed);
            $subdomain = str_ends_with($folder, '-sub') ? substr($folder, 0, -4) : $folder;

            if (preg_match('/^[a-z0-9-]+$/', $subdomain) !== 1 || trim($subdomain, '-') === '') {
                $warnings[] = "Invalid folder name '{$rawFolder}' skipped. Only a-z, 0-9, and hyphen (-) are allowed.";

                continue;
            }

            $valid[$subdomain] = true;
        }

        $subdomains = array_keys($valid);
        natcasesort($subdomains);

        return [
            'subdomains' => array_values($subdomains),
            'warnings' => $warnings,
        ];
    }

    /**
     * Normalize a domain name by stripping protocols, paths, and ports.
     */
    public function normalizeDomain(string $domain): string
    {
        $cleaned = trim($domain);
        $cleaned = (string) preg_replace('#^https?://#i', '', $cleaned);
        $cleaned = explode('/', $cleaned)[0];
        $cleaned = explode(':', $cleaned)[0];
        $cleaned = strtolower(trim($cleaned));

        if ($cleaned === '') {
            throw new RuntimeException('A valid domain is required to configure subdomain .htaccess rules.');
        }

        return $cleaned;
    }

    /**
     * Escape dots in the domain name for use in Apache RewriteCond regular expression.
     */
    public function escapeDomainForRegex(string $domain): string
    {
        $normalized = $this->normalizeDomain($domain);

        return (string) preg_replace('/(?<!\\\\)\./', '\.', $normalized);
    }
}
