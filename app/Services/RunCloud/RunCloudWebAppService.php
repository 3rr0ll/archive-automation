<?php

namespace App\Services\RunCloud;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RunCloudWebAppService
{
    public const DEFAULT_PHP_VERSION = 'php81rc';

    private const DEFAULT_DISABLED_FUNCTIONS = 'getmyuid,passthru,leak,listen,diskfreespace,tmpfile,link,ignore_user_abort,shell_exec,dl,set_time_limit,exec,system,highlight_file,source,show_source,fpassthru,virtual,posix_ctermid,posix_getcwd,posix_getegid,posix_geteuid,posix_getgid,posix_getgrgid,posix_getgrnam,posix_getgroups,posix_getlogin,posix_getpgid,posix_getpgrp,posix_getpid,posix,_getppid,posix_getpwuid,posix_getrlimit,posix_getsid,posix_getuid,posix_isatty,posix_kill,posix_mkfifo,posix_setegid,posix_seteuid,posix_setgid,posix_setpgid,posix_setsid,posix_setuid,posix_times,posix_ttyname,posix_uname,proc_open,proc_close,proc_nice,proc_terminate,escapeshellcmd,ini_alter,popen,pcntl_exec,socket_accept,socket_bind,socket_clear_error,socket_close,socket_connect,symlink,posix_geteuid,ini_alter,socket_listen,socket_create_listen,socket_read,socket_create_pair,stream_socket_server';

    public function __construct(
        protected RunCloudClient $client
    ) {}

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
     * Create the Empty/Custom Web Application with PHP 8.1 and public path /public_html.
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
            'phpVersion' => self::DEFAULT_PHP_VERSION,
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
     * Find the domain ID for a web application domain.
     */
    public function getDomainId(int $serverId, int $appId, string $domain): ?int
    {
        try {
            $response = $this->client->client()->get("/servers/{$serverId}/webapps/{$appId}/domains");

            if ($response->successful()) {
                $domains = $response->json('data') ?? $response->json() ?? [];
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
            }
        } catch (Exception $e) {
            Log::warning('Failed to retrieve domains list for webapp: '.$e->getMessage());
        }

        return null;
    }
}
