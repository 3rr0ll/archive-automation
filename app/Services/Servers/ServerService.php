<?php

namespace App\Services\Servers;

use App\Models\Server;
use App\Services\RunCloud\RunCloudService;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class ServerService
{
    public function __construct(
        protected RunCloudService $runCloudService
    ) {}

    /**
     * Get all servers ordered by name.
     *
     * @return Collection<int, Server>
     */
    public function getAll(): Collection
    {
        return Server::query()->orderBy('name')->get();
    }

    /**
     * Sync servers from RunCloud API into local database.
     * Updates or creates records using server_id, keeping existing is_active flag.
     *
     * @return Collection<int, Server>
     * @throws Exception
     */
    public function syncFromRunCloud(): Collection
    {
        $remoteServers = $this->runCloudService->getServers();

        foreach ($remoteServers as $remote) {
            $serverId = (int) ($remote['id'] ?? 0);
            if (! $serverId) {
                continue;
            }

            $name = (string) ($remote['name'] ?? "Server #{$serverId}");
            $ipAddress = (string) ($remote['ipAddress'] ?? $remote['ip_address'] ?? '');

            $existing = Server::where('server_id', $serverId)->first();

            if ($existing) {
                $existing->update([
                    'name' => $name,
                    'ip_address' => $ipAddress,
                ]);
            } else {
                Server::create([
                    'server_id' => $serverId,
                    'name' => $name,
                    'ip_address' => $ipAddress,
                    'is_active' => true,
                ]);
            }
        }

        return $this->getAll();
    }

    /**
     * Toggle or update only the active/inactive status of a server.
     *
     * @param Server $server
     * @param bool|null $isActive
     * @return Server
     */
    public function toggleActive(Server $server, ?bool $isActive = null): Server
    {
        $newStatus = $isActive !== null ? $isActive : ! $server->is_active;

        $server->update([
            'is_active' => $newStatus,
        ]);

        return $server->fresh();
    }
}
