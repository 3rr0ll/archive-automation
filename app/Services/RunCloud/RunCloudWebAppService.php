<?php

namespace App\Services\RunCloud;

use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RunCloudWebAppService
{
    public const DEFAULT_PHP_VERSION = 'php81rc';

    private const DEFAULT_DISABLED_FUNCTIONS = 'getmyuid,passthru,leak,listen,diskfreespace,tmpfile,link,ignore_user_abort,shell_exec,dl,set_time_limit,exec,system,highlight_file,source,show_source,fpassthru,virtual,posix_ctermid,posix_getcwd,posix_getegid,posix_geteuid,posix_getgid,posix_getgrgid,posix_getgrnam,posix_getgroups,posix_getlogin,posix_getpgid,posix_getpgrp,posix_getpid,posix,_getppid,posix_getpwuid,posix_getrlimit,posix_getsid,posix_getuid,posix_isatty,posix_kill,posix_mkfifo,posix_setegid,posix_seteuid,posix_setgid,posix_setpgid,posix_setsid,posix_setuid,posix_times,posix_ttyname,posix_uname,proc_open,proc_close,proc_nice,proc_terminate,escapeshellcmd,ini_alter,popen,pcntl_exec,socket_accept,socket_bind,socket_clear_error,socket_close,socket_connect,symlink,posix_geteuid,ini_alter,socket_listen,socket_create_listen,socket_read,socket_create_pair,stream_socket_server';

    /**
     * Minimum gap between lookups and deletes, about 55 requests a minute. RunCloud reports a
     * limit of 120, but under load it has only accepted about 60 a minute.
     */
    private const MIN_REQUEST_INTERVAL_SECONDS = 1.1;

    /**
     * Longest Retry-After worth waiting for inside a web request. RunCloud can ask for several
     * minutes, which would outlast the proxy timeout.
     */
    private const MAX_RETRY_WAIT_SECONDS = 15;

    private float $lastRequestAt = 0.0;

    public function __construct(
        protected RunCloudClient $client
    ) {}

    /**
     * Resolve the PHP version from the EnvironmentVariable model, then config/.env.
     */
    public function defaultPhpVersion(): string
    {
        $version = $this->client->getEnvValue(
            'RUNCLOUD_DEFAULT_PHP_VERSION',
            Config::get('services.runcloud.default_php_version', self::DEFAULT_PHP_VERSION),
        );

        return is_string($version) && trim($version) !== ''
            ? trim($version)
            : self::DEFAULT_PHP_VERSION;
    }

    /**
     * Rule 1: Generate RunCloud Web Application Name.
     * - Removes TLD (.com, .net, etc.)
     * - Max 30 characters
     */
    public function generateWebAppName(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = explode('/', $domain)[0];
        $domain = explode(':', $domain)[0];

        $parts = explode('.', $domain);
        $nameWithoutTld = $parts[0] ?? $domain;

        $cleanName = preg_replace('/[^a-z0-9_-]/', '', $nameWithoutTld) ?? '';

        if ($cleanName === '') {
            $cleanName = 'app'.Str::random(6);
        }

        return substr($cleanName, 0, 30);
    }

    /**
     * Normalize domain string to ensure no version prefixes or paths leak into RunCloud.
     */
    public function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = explode('/', $domain)[0];
        $domain = explode(':', $domain)[0];

        if (str_starts_with($domain, 'www.')) {
            $domain = substr($domain, 4);
        }

        return $domain;
    }

    /**
     * Check if domain or application name already exists on the server.
     */
    public function findExistingWebApplication(int $serverId, string $domain, string $appName): ?array
    {
        try {
            $response = $this->client->client()->get("/servers/{$serverId}/webapps");

            if ($response->successful()) {
                $apps = $response->json('data') ?? $response->json() ?? [];

                foreach ($apps as $app) {
                    $existingName = strtolower($app['name'] ?? '');
                    $existingDomain = strtolower($app['domainName'] ?? '');

                    if ($existingName === strtolower($appName) || $existingDomain === strtolower($domain)) {
                        return $app;
                    }
                }
            }
        } catch (Exception $e) {
            Log::warning('RunCloud duplicate check failed to fetch webapps list: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Resolve a web application for destructive operations without hiding API failures.
     */
    public function resolveWebApplication(int $serverId, string $domain, string $appName): ?array
    {
        $response = $this->client->client()->get("/servers/{$serverId}/webapps", [
            'search' => $appName,
            'perPage' => 40,
        ]);

        if (! $response->successful()) {
            $this->client->handleApiError('RunCloud webapp lookup failed', $response);
        }

        $apps = $response->json('data') ?? $response->json() ?? [];
        $normalizedDomain = $this->normalizeDomain($domain);

        foreach ($apps as $app) {
            $existingName = strtolower((string) ($app['name'] ?? ''));
            $domains = array_filter([
                $app['domainName'] ?? null,
                $app['primaryDomain']['name'] ?? null,
                ...array_column($app['domains'] ?? [], 'name'),
            ]);
            $domainMatches = collect($domains)->contains(
                fn (mixed $candidate): bool => $this->normalizeDomain((string) $candidate) === $normalizedDomain
            );

            if ($existingName === strtolower($appName) || $domainMatches) {
                return $app;
            }
        }

        return null;
    }

    /**
     * Load up to $maxPages more pages of a server's web apps into the cached index.
     *
     * RunCloud always returns 20 apps per page, so a listing can take many requests. Progress
     * is cached after every call, so a large server is loaded across several short requests
     * and a failed page can be resumed.
     *
     * @return array{complete: bool, failed: bool, fetched_pages: int, loaded_pages: int, total_pages: ?int}
     */
    public function buildWebAppIndex(int $serverId, int $maxPages): array
    {
        $cache = $this->client->cache();
        $index = $cache->runCloudWebAppIndex($serverId)
            ?? ['apps' => [], 'next_page' => 1, 'total_pages' => null, 'complete' => false];
        $fetchedPages = 0;
        $failed = false;

        while (! $index['complete'] && $fetchedPages < $maxPages) {
            $response = $this->rateLimitedGet("/servers/{$serverId}/webapps", ['page' => $index['next_page']]);

            if (! $response?->successful()) {
                $failed = true;
                break;
            }

            foreach ($response->json('data') ?? [] as $app) {
                if (isset($app['id'], $app['name'])) {
                    $index['apps'][strtolower((string) $app['name'])] = [
                        'id' => $app['id'],
                        'name' => $app['name'],
                        'created_at' => $app['created_at'] ?? null,
                    ];
                }
            }

            $index['total_pages'] = max(1, (int) $response->json('meta.pagination.total_pages'));
            $index['complete'] = $index['next_page'] >= $index['total_pages'];
            $index['next_page']++;
            $fetchedPages++;
        }

        if ($fetchedPages > 0) {
            $cache->putRunCloudWebAppIndex($serverId, $index);
        }

        return [
            'complete' => $index['complete'],
            'failed' => $failed,
            'fetched_pages' => $fetchedPages,
            'loaded_pages' => $index['next_page'] - 1,
            'total_pages' => $index['total_pages'],
        ];
    }

    /**
     * Find a domain's web application, using the cached index when the server has a complete one.
     *
     * With an index, a domain whose conventional app name is not listed costs no request, and a
     * name match costs one. Apps with an unconventional name are only found by a deep search.
     *
     * @throws RuntimeException when RunCloud could not answer, so the domain's state is unknown
     */
    public function locateWebApplication(int $serverId, string $domain, bool $deep = false): ?array
    {
        $index = $deep ? null : $this->client->cache()->runCloudWebAppIndex($serverId);

        if (! ($index['complete'] ?? false)) {
            return $this->findWebApplicationByDomain($serverId, $domain);
        }

        $normalizedDomain = $this->normalizeDomain($domain);
        $app = $index['apps'][$this->generateWebAppName($normalizedDomain)] ?? null;

        return $app === null ? null : $this->confirmWebAppDomain($serverId, $app, $normalizedDomain);
    }

    /**
     * Find the web application serving a domain on a server.
     *
     * RunCloud's search matches both app names and attached domain names, so a single
     * search request is enough. List results do not include domains, so candidates are
     * confirmed against their domain list before being returned (the result is used for
     * destructive operations, so a name-only match is not trusted).
     *
     * @throws RuntimeException when RunCloud could not answer, so the domain's state is unknown
     */
    public function findWebApplicationByDomain(int $serverId, string $domain): ?array
    {
        $normalizedDomain = $this->normalizeDomain($domain);
        $appName = $this->generateWebAppName($normalizedDomain);

        $response = $this->rateLimitedGet("/servers/{$serverId}/webapps", ['search' => $normalizedDomain]);

        if (! $response?->successful()) {
            throw new RuntimeException("RunCloud search failed on server {$serverId}.");
        }

        $apps = $response->json('data') ?? [];

        foreach ($apps as $app) {
            if ($this->webAppMatchesDomain($app, $normalizedDomain)) {
                return $app;
            }
        }

        // Check the most likely candidates first: the conventional app name, then the rest.
        $candidates = collect($apps)
            ->filter(fn (array $app): bool => isset($app['id']))
            ->sortByDesc(fn (array $app): bool => strtolower((string) ($app['name'] ?? '')) === $appName)
            ->take(3);

        $lookupError = null;

        foreach ($candidates as $app) {
            try {
                $confirmed = $this->confirmWebAppDomain($serverId, $app, $normalizedDomain);
            } catch (RuntimeException $exception) {
                $lookupError = $exception;

                continue;
            }

            if ($confirmed !== null) {
                return $confirmed;
            }
        }

        if ($lookupError !== null) {
            throw $lookupError;
        }

        return null;
    }

    /**
     * Return the app with its domains if the domain is attached to it. A 404 means the app
     * was removed since it was listed.
     *
     * @throws RuntimeException when RunCloud could not answer
     */
    private function confirmWebAppDomain(int $serverId, array $app, string $normalizedDomain): ?array
    {
        $response = $this->rateLimitedGet("/servers/{$serverId}/webapps/{$app['id']}/domains");

        if ($response?->status() === 404) {
            return null;
        }

        if (! $response?->successful()) {
            throw new RuntimeException("RunCloud domain lookup failed for app {$app['id']}.");
        }

        $app['domains'] = $response->json('data') ?? [];

        return $this->webAppMatchesDomain($app, $normalizedDomain) ? $app : null;
    }

    /**
     * GET request that waits and retries when RunCloud's rate limit (120 req/min) is hit.
     *
     * A long Retry-After is not waited out: lookups stop until it passes, and return null so
     * callers report them as failed.
     *
     * @param  array<string, mixed>  $query
     */
    private function rateLimitedGet(string $url, array $query = []): ?Response
    {
        if ($this->client->cache()->isRunCloudRateLimited()) {
            return null;
        }

        $this->pace();

        try {
            return $this->client->client()
                ->retry(
                    3,
                    fn (int $attempt, Throwable $e): int => $e instanceof RequestException
                        ? max(1, (int) $e->response->header('Retry-After')) * 1000
                        : 1000,
                    fn (Throwable $e): bool => $e instanceof RequestException
                        && $e->response->status() === 429
                        && $this->shouldWaitForRateLimit($e->response),
                    throw: false,
                )
                ->get($url, $query);
        } catch (Throwable $e) {
            Log::warning("RunCloud lookup failed for {$url}: ".$e->getMessage());

            return null;
        }
    }

    private function shouldWaitForRateLimit(Response $response): bool
    {
        $retryAfter = (int) $response->header('Retry-After');

        if ($retryAfter <= self::MAX_RETRY_WAIT_SECONDS) {
            return true;
        }

        $this->client->cache()->markRunCloudRateLimited($retryAfter);

        return false;
    }

    private function pace(): void
    {
        $wait = $this->lastRequestAt + self::MIN_REQUEST_INTERVAL_SECONDS - microtime(true);

        if ($wait > 0) {
            Sleep::usleep((int) ($wait * 1_000_000));
        }

        $this->lastRequestAt = microtime(true);
    }

    private function webAppMatchesDomain(array $app, string $normalizedDomain): bool
    {
        $domains = array_filter([
            $app['domainName'] ?? null,
            $app['primaryDomain']['name'] ?? null,
            ...array_column($app['domains'] ?? [], 'name'),
        ]);

        $domainMatches = collect($domains)->contains(
            fn (mixed $candidate): bool => $this->normalizeDomain((string) $candidate) === $normalizedDomain
        );

        return $domainMatches;
    }

    /**
     * Delete a web application. A missing app is already in the desired state.
     */
    public function deleteWebApplication(int $serverId, int $webAppId): void
    {
        $this->pace();

        $response = $this->client->client()->delete("/servers/{$serverId}/webapps/{$webAppId}");

        if ($response->status() === 404) {
            return;
        }

        if (! $response->successful()) {
            $this->client->handleApiError('RunCloud delete webapp failed', $response);
        }
    }

    /**
     * Attempt to create custom web app, detecting 422 duplicate/conflict responses without an upfront GET.
     */
    public function attemptCreateCustomWebApp(int $serverId, string $webAppName, string $domain, int $systemUserId): array
    {
        $payload = $this->customWebAppPayload($webAppName, $domain, $systemUserId);

        $response = $this->client->client()->post("/servers/{$serverId}/webapps/custom", $payload);

        if ($response->status() === 422) {
            $responseBody = $response->json();
            $message = json_encode($responseBody);

            $isDuplicate = str_contains(strtolower($message), 'already been taken')
                || str_contains(strtolower($message), 'already taken')
                || str_contains(strtolower($message), 'already exists')
                || str_contains(strtolower($message), 'has already been used');

            if ($isDuplicate) {
                return [
                    'success' => false,
                    'is_duplicate' => true,
                    'message' => $responseBody['message'] ?? 'Web application or domain already exists on RunCloud.',
                    'data' => $responseBody,
                ];
            }
        }

        if (! $response->successful()) {
            $this->client->handleApiError('RunCloud create webapp failed', $response);
        }

        return [
            'success' => true,
            'is_duplicate' => false,
            'data' => $response->json('data') ?? $response->json() ?? [],
        ];
    }

    /**
     * Create the Empty/Custom Web Application with the configured PHP version and public path /public_html.
     */
    public function createCustomWebApp(int $serverId, string $webAppName, string $domain, int $systemUserId): array
    {
        $payload = $this->customWebAppPayload($webAppName, $domain, $systemUserId);

        $response = $this->client->client()->post("/servers/{$serverId}/webapps/custom", $payload);

        if (! $response->successful()) {
            $this->client->handleApiError('RunCloud create webapp failed', $response);
        }

        return $response->json('data') ?? $response->json() ?? [];
    }

    /**
     * Build the RunCloud custom web application payload with secure NGINX and PHP defaults.
     *
     * @return array<string, bool|int|string>
     */
    public function customWebAppPayload(string $webAppName, string $domain, int $systemUserId): array
    {
        return [
            'name' => $webAppName,
            'domainName' => $domain,
            'www' => true,
            'redirection' => 'none',
            'user' => $systemUserId,
            'publicPath' => '/public_html',
            'phpVersion' => $this->defaultPhpVersion(),
            'stack' => 'hybrid',
            'stackMode' => 'production',
            'clickjackingProtection' => true,
            'xssProtection' => true,
            'mimeSniffingProtection' => true,
            'processManager' => 'dynamic',
            'processManagerStartServers' => 1,
            'processManagerMinSpareServers' => 1,
            'processManagerMaxSpareServers' => 1,
            'processManagerMaxChildren' => 5,
            'processManagerMaxRequests' => 500,
            'openBasedir' => "/home/runcloud/webapps/{$webAppName}:/var/lib/php/session:/tmp",
            'timezone' => 'UTC',
            'disableFunctions' => self::DEFAULT_DISABLED_FUNCTIONS,
            'maxExecutionTime' => 30,
            'maxInputTime' => 60,
            'maxInputVars' => 1000,
            'memoryLimit' => 256,
            'postMaxSize' => 256,
            'uploadMaxFilesize' => 256,
            'sessionGcMaxlifetime' => 1440,
            'allowUrlFopen' => true,
        ];
    }

    /**
     * Configure Domain, enable WWW for this domain/subdomain, and leave the preferred domain version unset (redirection: none).
     */
    public function configureDomain(int $serverId, int $appId, string $domain, ?int $domainId = null): array
    {
        $payload = [
            'name' => $domain,
            'www' => true,
            'redirection' => 'none',
            'type' => 'primary',
        ];

        // 1. If domainId is provided, attempt updating existing primary domain configuration
        if ($domainId) {
            // RunCloud API v3 uses PATCH .../domains/{domainId}/meta for updating domain metadata
            $updateResponse = $this->client->client()->patch("/servers/{$serverId}/webapps/{$appId}/domains/{$domainId}/meta", $payload);
            if (! $updateResponse->successful()) {
                $updateResponse = $this->client->client()->put("/servers/{$serverId}/webapps/{$appId}/domains/{$domainId}", $payload);
            }
            if ($updateResponse->status() === 405 || $updateResponse->status() === 404) {
                $updateResponse = $this->client->client()->patch("/servers/{$serverId}/webapps/{$appId}/domains/{$domainId}", $payload);
            }

            if ($updateResponse->successful()) {
                Log::info('Successfully updated domain settings (www enabled, preferred version unset)', [
                    'server_id' => $serverId,
                    'app_id' => $appId,
                    'domain_id' => $domainId,
                    'domain' => $domain,
                ]);

                return $updateResponse->json('data') ?? $updateResponse->json() ?? [];
            }

            Log::warning('RunCloud domain update returned non-successful response', [
                'server_id' => $serverId,
                'app_id' => $appId,
                'domain_id' => $domainId,
                'domain' => $domain,
                'status' => $updateResponse->status(),
                'body' => $updateResponse->json() ?? $updateResponse->body(),
            ]);
        }

        // 2. Attach domain with www flag
        $response = $this->client->client()->post("/servers/{$serverId}/webapps/{$appId}/domains", $payload);

        if ($response->status() === 422 || ! $response->successful()) {
            Log::info('RunCloud POST /domains returned non-successful response', [
                'server_id' => $serverId,
                'app_id' => $appId,
                'domain' => $domain,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);
        }

        // 3. If primary domain was already attached without www, attach "www." as an alias domain
        if (! str_starts_with($domain, 'www.')) {
            $wwwDomain = 'www.'.$domain;
            try {
                $aliasResponse = $this->client->client()->post("/servers/{$serverId}/webapps/{$appId}/domains", [
                    'name' => $wwwDomain,
                    'type' => 'alias',
                ]);

                if ($aliasResponse->successful()) {
                    Log::info('Successfully attached www alias domain', [
                        'server_id' => $serverId,
                        'app_id' => $appId,
                        'alias' => $wwwDomain,
                    ]);
                } else {
                    Log::info('RunCloud attach www alias domain response', [
                        'server_id' => $serverId,
                        'app_id' => $appId,
                        'alias' => $wwwDomain,
                        'status' => $aliasResponse->status(),
                        'body' => $aliasResponse->json() ?? $aliasResponse->body(),
                    ]);
                }
            } catch (Exception $e) {
                Log::warning('Could not attach www alias domain: '.$e->getMessage());
            }
        }

        return $response->json('data') ?? $response->json() ?? [];
    }

    /**
     * Get all domains attached to a web application.
     *
     * @return list<array<string, mixed>>
     */
    public function getWebAppDomains(int $serverId, int $appId): array
    {
        try {
            $response = $this->client->client()->get("/servers/{$serverId}/webapps/{$appId}/domains");

            if ($response->successful()) {
                $domains = $response->json('data') ?? $response->json() ?? [];

                return is_array($domains) ? array_values($domains) : [];
            }

            Log::warning("Failed to retrieve domains list for webapp {$appId}: status ".$response->status());
        } catch (Throwable $e) {
            Log::warning("Failed to retrieve domains list for webapp {$appId}: ".$e->getMessage());
        }

        return [];
    }

    /**
     * Attach a subdomain as an alias in RunCloud with Cloudflare DNS integration and proxy settings.
     *
     * @return array<string, mixed>
     */
    public function attachSubdomainAlias(
        int $serverId,
        int $appId,
        string $subdomain,
        ?int $externalApiId = null,
    ): array {
        $externalApiId = $externalApiId
            ?: (int) ($this->client->getEnvValue(
                'RUNCLOUD_CLOUDFLARE_EXTERNAL_API_ID',
                Config::get('services.runcloud.cloudflare_external_api_id', 42310)
            ) ?: 42310);

        $payload = [
            'name' => $subdomain,
            'domainName' => $subdomain,
            'type' => 'alias',
            'domainType' => 'alias',
            'www' => false,
            'redirection' => 'none',
            'allowDnsIntegration' => true,
            'setAsProxied' => true,
            'proxy' => true,
            'proxied' => true,
        ];

        if ($externalApiId) {
            $payload['externalApi'] = $externalApiId;
        }

        $response = $this->client->client()->post("/servers/{$serverId}/webapps/{$appId}/domains", $payload);

        if (! $response->successful()) {
            if ($response->status() === 422) {
                $errorMsg = (string) ($response->json('message') ?? $response->body());
                if (str_contains(strtolower($errorMsg), 'already') || str_contains(strtolower($errorMsg), 'taken')) {
                    throw new RuntimeException("Subdomain [{$subdomain}] already attached on RunCloud.");
                }

                // Fallback attempt without extra convenience aliases if rejected by strict validation
                $fallbackPayload = [
                    'name' => $subdomain,
                    'type' => 'alias',
                    'www' => false,
                    'redirection' => 'none',
                    'allowDnsIntegration' => true,
                    'setAsProxied' => true,
                ];

                if ($externalApiId) {
                    $fallbackPayload['externalApi'] = $externalApiId;
                }

                $fallback = $this->client->client()->post("/servers/{$serverId}/webapps/{$appId}/domains", $fallbackPayload);
                if ($fallback->successful()) {
                    return $fallback->json('data') ?? $fallback->json() ?? [];
                }
            }

            $this->client->handleApiError("RunCloud attach subdomain alias failed for {$subdomain}", $response);
        }

        return $response->json('data') ?? $response->json() ?? [];
    }

    /**
     * Automatically add subdomain aliases for checked subfolders to a RunCloud web app.
     *
     * @param  list<string>  $subdomainFolders
     * @return array{
     *     added: list<array{subdomain: string, name: string, id: ?int}>,
     *     skipped: list<array{subdomain: string, name: string, reason: string}>,
     *     failed: list<array{subdomain: string, name: string, error: string}>,
     * }
     */
    public function attachSubdomainAliases(
        int $serverId,
        int $appId,
        string $domain,
        array $subdomainFolders,
        ?int $externalApiId = null,
    ): array {
        $results = [
            'added' => [],
            'skipped' => [],
            'failed' => [],
        ];

        if ($subdomainFolders === []) {
            return $results;
        }

        $cleanDomain = strtolower(trim(preg_replace('#^https?://#i', '', $domain)));
        $cleanDomain = explode('/', $cleanDomain)[0];
        $cleanDomain = explode(':', $cleanDomain)[0];

        // 1. Fetch existing domains attached to this web application to avoid duplicates
        $existingDomains = $this->getWebAppDomains($serverId, $appId);
        $existingNames = collect($existingDomains)
            ->pluck('name')
            ->filter(fn ($n) => is_string($n) && $n !== '')
            ->map(fn (string $n) => strtolower(trim($n)))
            ->all();

        // 2. Process each checked subfolder independently
        foreach ($subdomainFolders as $rawFolder) {
            if (! is_string($rawFolder)) {
                continue;
            }

            $trimmed = trim($rawFolder);
            if ($trimmed === '') {
                continue;
            }

            $lower = strtolower($trimmed);
            $name = str_ends_with($lower, '-sub') ? substr($lower, 0, -4) : $lower;

            // Validate subdomain label: a-z, 0-9, and - only
            if (preg_match('/^[a-z0-9-]+$/', $name) !== 1 || trim($name, '-') === '') {
                $results['failed'][] = [
                    'subdomain' => "{$name}.{$cleanDomain}",
                    'name' => $name,
                    'error' => "Invalid subdomain name '{$rawFolder}'. Only a-z, 0-9, and hyphen (-) are allowed.",
                ];

                continue;
            }

            $fullSubdomain = "{$name}.{$cleanDomain}";

            // Skip subdomains that already exist in RunCloud
            if (in_array(strtolower($fullSubdomain), $existingNames, true)) {
                $results['skipped'][] = [
                    'subdomain' => $fullSubdomain,
                    'name' => $name,
                    'reason' => 'Subdomain already attached in RunCloud.',
                ];

                continue;
            }

            // Attempt to add subdomain alias in RunCloud
            try {
                $response = $this->attachSubdomainAlias($serverId, $appId, $fullSubdomain, $externalApiId);
                $domainId = isset($response['id']) ? (int) $response['id'] : null;

                $results['added'][] = [
                    'subdomain' => $fullSubdomain,
                    'name' => $name,
                    'id' => $domainId,
                ];

                // Prevent duplicates within the same batch
                $existingNames[] = strtolower($fullSubdomain);
            } catch (Throwable $e) {
                Log::warning("RunCloud failed to attach subdomain alias [{$fullSubdomain}]: {$e->getMessage()}");

                $results['failed'][] = [
                    'subdomain' => $fullSubdomain,
                    'name' => $name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Find the domain ID for a web application domain.
     */
    public function getDomainId(int $serverId, int $appId, string $domain): ?int
    {
        $domains = $this->getWebAppDomains($serverId, $appId);
        $normalized = strtolower(trim($domain));

        foreach ($domains as $item) {
            $itemDomain = strtolower(trim($item['name'] ?? ''));
            if ($itemDomain === $normalized || str_contains($itemDomain, $normalized)) {
                return (int) $item['id'];
            }
        }

        if (! empty($domains[0]['id'])) {
            return (int) $domains[0]['id'];
        }

        return null;
    }
}
