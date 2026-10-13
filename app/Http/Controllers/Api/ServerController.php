<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServerRequest;
use App\Http\Requests\UpdateServerRequest;
use App\Http\Resources\ServerResource;
use App\Models\Server;
use App\Services\Cache\ApplicationCache;
use App\Services\Logging\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServerController extends Controller
{
    public function __construct(
        private ApplicationCache $cache,
        private AuditLogger $auditLogger,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $servers = collect($this->cache->rememberLocalServers(
            fn (): array => Server::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Server $server): array => $server->getAttributes())
                ->all(),
        ))->map(fn (array $attributes): Server => (new Server)->newFromBuilder($attributes));

        return ServerResource::collection(
            $servers,
        );
    }

    public function store(StoreServerRequest $request): JsonResponse
    {
        $server = Server::create($request->validated());
        $this->auditLogger->record('server.created', $server, [
            'runcloud_server_id' => $server->server_id,
        ]);

        return response()->json([
            'message' => 'Server created successfully.',
            'data' => new ServerResource($server),
        ], 201);
    }

    public function show(Server $server): ServerResource
    {
        return new ServerResource($server);
    }

    public function update(UpdateServerRequest $request, Server $server): JsonResponse
    {
        $server->update($request->validated());
        $this->auditLogger->record('server.updated', $server, [
            'changed_fields' => array_keys($server->getChanges()),
        ]);

        return response()->json([
            'message' => 'Server updated successfully.',
            'data' => new ServerResource($server->fresh()),
        ]);
    }

    public function destroy(Server $server): JsonResponse
    {
        if ($server->deployments()->exists()) {
            return response()->json([
                'message' => 'A server with deployment history cannot be deleted. Deactivate it instead.',
            ], 422);
        }

        $server->delete();
        $this->auditLogger->record('server.deleted', $server, [
            'runcloud_server_id' => $server->server_id,
        ]);

        return response()->json([
            'message' => 'Server deleted successfully.',
        ]);
    }
}
