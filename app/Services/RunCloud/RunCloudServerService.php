<?php

namespace App\Services\RunCloud;

use App\Models\Server;
use Exception;
use Illuminate\Support\Facades\Config;

class RunCloudServerService
{
    public function __construct(
        protected RunCloudClient $client
    ) {}

    /**
     * Get all connected servers from the RunCloud account.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getServers(): array
    {
        return $this->client->cache()->rememberRunCloudServers(function (): array {
            $response = $this->client->client()->get('/servers');

            if (! $response->successful()) {
                $this->client->handleApiError('Failed to fetch RunCloud servers', $response);
            }

            return $response->json('data') ?? $response->json() ?? [];
        });
    }

    /**
     * Get details of a specific RunCloud server.
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function getServer(int $serverId): array
    {
        return $this->client->cache()->rememberRunCloudServer($serverId, function () use ($serverId): array {
            $response = $this->client->client()->get("/servers/{$serverId}");

            if (! $response->successful()) {
                $this->client->handleApiError("Failed to fetch RunCloud server {$serverId}", $response);
            }

            return $response->json('data') ?? $response->json() ?? [];
        });
    }

    /**
     * Resolve the public IP address for a RunCloud server.
     * Uses Server model first (0 API credits), then EnvironmentVariable, then Config, with caching.
     *
     * @throws Exception
     */
    public function getServerIp(?int $serverId = null): string
    {
        // 1. Check local Server model (0 API credits)
        if ($serverId) {
            $serverModel = Server::where('server_id', $serverId)->first();
            if ($serverModel && ! empty($serverModel->ip_address)) {
                return (string) $serverModel->ip_address;
            }
        }

        // 2. Direct configuration override if provided (EnvironmentVariable first, then Config)
        $configuredIp = $this->client->getEnvValue('RUNCLOUD_DEFAULT_SERVER_IP')
            ?? $this->client->getEnvValue('RUNCLOUD_SERVER_IP')
            ?? Config::get('services.runcloud.server_ip');

        if (! empty($configuredIp)) {
            return (string) $configuredIp;
        }

        // 3. Fallback to active server model in DB
        $activeServer = Server::where('is_active', true)->first() ?? Server::first();
        if ($activeServer && ! empty($activeServer->ip_address)) {
            return (string) $activeServer->ip_address;
        }

        $defaultServerId = $this->client->getEnvValue('RUNCLOUD_DEFAULT_SERVER_ID')
            ?? $this->client->getEnvValue('RUNCLOUD_SERVER_ID')
            ?? Config::get('services.runcloud.server_id');

        $server = $serverId ?? ($defaultServerId ? (int) $defaultServerId : null);

        // 4. If still not found, check cache or query RunCloud API as last resort
        if (! $server) {
            $servers = $this->getServers();
            $serverData = $servers[0] ?? [];
            $ip = $serverData['ipAddress'] ?? $serverData['ip_address'] ?? $serverData['ip'] ?? null;
            if (! empty($ip)) {
                return (string) $ip;
            }
            $server = (int) ($serverData['id'] ?? 0);
        }

        if (! $server) {
            throw new Exception('No RunCloud server ID configured or found.');
        }

        return $this->client->cache()->rememberRunCloudServerIp($server, function () use ($server): string {
            $serverDetails = $this->getServer($server);
            $ip = $serverDetails['ipAddress'] ?? $serverDetails['ip_address'] ?? $serverDetails['ip'] ?? null;

            if (empty($ip)) {
                throw new Exception("Unable to determine IP address for RunCloud server {$server}.");
            }

            return (string) $ip;
        });
    }

    /**
     * Resolve system user "runcloud" ID on the server.
     * Checks EnvironmentVariable (RUNCLOUD_USER_ID) and Cache first (0 API requests).
     *
     * @throws Exception
     */
    public function getRunCloudSystemUserId(int $serverId): int
    {
        // 1. Check EnvironmentVariable model or Config first (0 API calls)
        $configuredUserId = $this->client->getEnvValue('RUNCLOUD_USER_ID')
            ?? $this->client->getEnvValue('RUNCLOUD_SYSTEM_USER_ID')
            ?? Config::get('services.runcloud.user_id');

        if (! empty($configuredUserId)) {
            return (int) $configuredUserId;
        }

        // 2. Cache the result if fetched from API so it only ever calls once across all deployments
        return $this->client->cache()->rememberRunCloudSystemUserId($serverId, function () use ($serverId): int {
            $response = $this->client->client()->get("/servers/{$serverId}/users");

            if (! $response->successful()) {
                $this->client->handleApiError("Failed to fetch RunCloud system users for server {$serverId}", $response);
            }

            $users = $response->json('data') ?? $response->json() ?? [];

            foreach ($users as $user) {
                if (strtolower($user['username'] ?? '') === 'runcloud') {
                    return (int) $user['id'];
                }
            }

            throw new Exception("RunCloud system user 'runcloud' was not found on server {$serverId}.");
        });
    }
}
