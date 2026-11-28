<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServerResource;
use App\Models\Server;
use App\Services\Cache\ApplicationCache;
use App\Services\Logging\AuditLogger;
use App\Services\Servers\ServerService;
use App\Services\Servers\ServerWebsiteSyncService;
use Exception;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;

class ServerController extends Controller
{
    public function __construct(
        protected ServerService $serverService,
        private ApplicationCache $cache,
        private AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $servers = $request->user()?->isAdmin()
            ? $this->serverService->getAll()
            : $this->serverService->getActive();

        return ServerResource::collection(
            $servers
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

    public function syncWebsites(Request $request, Server $server, ServerWebsiteSyncService $websiteSync): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ]);

        try {
            $result = $websiteSync->sync(
                $server,
                $request->user(),
                (int) ($validated['page'] ?? 1),
                (int) ($validated['offset'] ?? 0),
            );
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'Another website sync is saving. Try again shortly.'], 409);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => 'Could not finish syncing websites. Try again shortly.'], 502);
        }

        return response()->json(['data' => $result]);
    }
}
