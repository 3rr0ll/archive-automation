<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServerResource;
use App\Models\Server;
use App\Services\Servers\ServerService;
use Exception;
use App\Services\Cache\ApplicationCache;
use App\Services\Logging\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServerController extends Controller
{
    public function __construct(
        protected ServerService $serverService,
        private ApplicationCache $cache,
        private AuditLogger $auditLogger,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return ServerResource::collection(
            $this->serverService->getAll()
        );
    }

    public function show(Server $server): ServerResource
    {
        return new ServerResource($server);
    }

    /**
     * Sync servers from RunCloud to the local database.
     */
    public function sync(): JsonResponse
    {
        try {
            $servers = $this->serverService->syncFromRunCloud();

            return response()->json([
                'message' => 'Servers synced successfully from RunCloud.',
                'data' => ServerResource::collection($servers),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to sync servers from RunCloud.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle or set active/inactive status of a server.
     */
    public function toggleActive(Request $request, Server $server): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => 'nullable|boolean',
        ]);

        $isActive = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : null;

        $updated = $this->serverService->toggleActive($server, $isActive);

        return response()->json([
            'message' => $updated->is_active ? 'Server activated.' : 'Server deactivated.',
            'data' => new ServerResource($updated),
        ]);
    }
}
