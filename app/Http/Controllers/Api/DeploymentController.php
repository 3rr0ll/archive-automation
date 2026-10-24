<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDeploymentIndexRequest;
use App\Http\Requests\BulkDeploymentRequest;
use App\Http\Requests\DeploymentLogsRequest;
use App\Http\Requests\StoreDeploymentRequest;
use App\Http\Resources\DeploymentResource;
use App\Models\Deployment;
use App\Models\Server;
use App\Services\Deployment\DeploymentLifecycleService;
use App\Services\Deployment\DeploymentStatisticsService;
use App\Services\RunCloud\RunCloudService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeploymentController extends Controller
{
    public function __construct(
        private DeploymentLifecycleService $lifecycleService,
        private DeploymentStatisticsService $statisticsService,
        private RunCloudService $runCloudService,
    ) {}

    /**
     * Display deployments across all users for administrators.
     */
    public function adminIndex(AdminDeploymentIndexRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $deployments = $this->filteredAdminQuery($validated)
            ->with(['user:id,name,email', 'server'])
            ->orderBy($validated['sort_by'] ?? 'created_at', $validated['sort_direction'] ?? 'desc')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return DeploymentResource::collection($deployments);
    }

    public function statistics(AdminDeploymentIndexRequest $request): JsonResponse
    {
        $filters = $request->safe()->except([
            'sort_by',
            'sort_direction',
            'per_page',
        ]);
        $query = $this->filteredAdminQuery($filters);

        return response()->json([
            'data' => $this->statisticsService->calculate($query, $filters),
        ]);
    }

    /**
     * Display a deployment and its logs for an administrator.
     */
    public function adminShow(Deployment $deployment): DeploymentResource
    {
        return new DeploymentResource($deployment->load([
            'user:id,name,email',
            'server',
            'logs' => fn ($query) => $query->oldest(),
        ]));
    }

    public function logs(DeploymentLogsRequest $request, Deployment $deployment): JsonResponse
    {
        $validated = $request->validated();
        $logs = $deployment->logs()
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(isset($validated['step_name']), fn ($query) => $query->where('step_name', $validated['step_name']))
            ->orderBy('created_at', $validated['sort_direction'] ?? 'desc')
            ->orderBy('id', $validated['sort_direction'] ?? 'desc')
            ->paginate($validated['per_page'] ?? 25)
            ->withQueryString();

        return response()->json([
            'data' => $logs->getCollection()->map(fn ($log): array => [
                'id' => $log->id,
                'deployment_id' => $log->deployment_id,
                'step_name' => $log->step_name,
                'status' => $log->status,
                'message' => $log->message,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    public function cancel(Deployment $deployment): JsonResponse
    {
        try {
            $deployment = $this->lifecycleService->cancel($deployment);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => new DeploymentResource($deployment->load('user:id,name,email', 'logs')),
            'message' => 'Deployment cancelled.',
        ]);
    }

    public function retry(Deployment $deployment): JsonResponse
    {
        try {
            $deployment = $this->lifecycleService->retry($deployment);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => new DeploymentResource($deployment->load('user:id,name,email', 'logs')),
            'message' => 'Deployment retry queued.',
        ], 202);
    }

    public function adminDestroy(Deployment $deployment): JsonResponse
    {
        try {
            $this->lifecycleService->delete($deployment);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Deployment deleted.',
        ]);
    }

    public function bulkCancel(BulkDeploymentRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Bulk cancellation completed.',
            'data' => $this->lifecycleService->bulkCancel($request->validated('ids')),
        ]);
    }

    public function bulkRetry(BulkDeploymentRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Bulk retry completed.',
            'data' => $this->lifecycleService->bulkRetry($request->validated('ids')),
        ], 202);
    }

    public function bulkSearchDomains(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domains' => ['required', 'array', 'min:1', 'max:500'],
            'domains.*' => ['required', 'string', 'max:255'],
        ]);

        $rawDomains = collect($validated['domains'])
            ->map(fn ($domain) => strtolower(trim((string) $domain)))
            ->filter()
            ->unique()
            ->values();

        if ($rawDomains->isEmpty()) {
            return response()->json([
                'data' => [
                    'found' => [],
                    'missing' => [],
                ],
            ]);
        }

        // 1. Query deployments matching the exact domain (case-insensitive) in local database
        $deployments = Deployment::query()
            ->whereIn('domain', $rawDomains->all())
            ->with(['user:id,name,email', 'server'])
            ->orderBy('domain')
            ->orderByDesc('id')
            ->get();

        $foundItems = collect(DeploymentResource::collection($deployments)->resolve())
            ->map(function ($item) {
                $item['source'] = 'database';
                return $item;
            });

        $foundDomains = $deployments->pluck('domain')->map(fn ($d) => strtolower($d))->unique();
        $missingFromDb = $rawDomains->diff($foundDomains)->values();

        // 2. For domains not found in database, check RunCloud servers
        $runCloudFound = collect();
        $stillMissing = collect();

        if ($missingFromDb->isNotEmpty()) {
            // Check all servers registered in database as well as live RunCloud servers
            $serversById = Server::query()->get()->keyBy('server_id');

            try {
                $liveRcServers = $this->runCloudService->getServers();
                foreach ($liveRcServers as $liveServer) {
                    $sid = (int) ($liveServer['id'] ?? 0);
                    if ($sid && ! $serversById->has($sid)) {
                        $serversById->put($sid, (object) [
                            'id' => null,
                            'server_id' => $sid,
                            'name' => $liveServer['name'] ?? "Server {$sid}",
                            'ip_address' => $liveServer['ipAddress'] ?? null,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                // If live RunCloud server listing fails, proceed with database servers
            }

            $servers = $serversById->values();

            foreach ($missingFromDb as $domain) {
                $appName = $this->runCloudService->generateWebAppName($domain);
                $matchedApp = null;
                $matchedServer = null;

                foreach ($servers as $server) {
                    try {
                        $app = $this->runCloudService->findWebApplicationByDomain(
                            (int) $server->server_id,
                            $domain
                        );

                        if ($app) {
                            $matchedApp = $app;
                            $matchedServer = $server;
                            break;
                        }
                    } catch (\Throwable $e) {
                        // Continue to next server if one fails
                    }
                }

                if ($matchedApp && $matchedServer) {
                    $runCloudFound->push([
                        'id' => 'rc_'.$matchedServer->server_id.'_'.$matchedApp['id'],
                        'hash' => null,
                        'user_id' => null,
                        'user' => null,
                        'server_id' => $matchedServer->server_id,
                        'server' => [
                            'id' => $matchedServer->id ?? null,
                            'server_id' => $matchedServer->server_id,
                            'name' => $matchedServer->name,
                            'ip_address' => $matchedServer->ip_address,
                        ],
                        'domain' => $domain,
                        'version' => 'non-www',
                        'archive_source' => 'runcloud',
                        'archive_original_name' => null,
                        'archive_status' => null,
                        'status' => 'runcloud',
                        'current_step' => 'runcloud_app',
                        'source' => 'runcloud',
                        'runcloud_web_app_id' => $matchedApp['id'],
                        'runcloud_web_app_name' => $matchedApp['name'] ?? $appName,
                        'created_at' => $matchedApp['created_at'] ?? now()->toIso8601String(),
                        'updated_at' => $matchedApp['updated_at'] ?? now()->toIso8601String(),
                    ]);
                } else {
                    $stillMissing->push($domain);
                }
            }
        }

        $allFound = $foundItems->concat($runCloudFound)->sortBy('domain')->values();

        return response()->json([
            'data' => [
                'found' => $allFound,
                'missing' => $stillMissing->values(),
            ],
        ]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required'],
        ]);

        $rawIds = $validated['ids'];
        $dbIds = [];
        $runCloudItems = [];

        foreach ($rawIds as $id) {
            if (is_numeric($id)) {
                $dbIds[] = (int) $id;
            } elseif (is_string($id) && str_starts_with($id, 'rc_')) {
                // Format: rc_{serverId}_{webAppId}
                $parts = explode('_', $id);
                if (count($parts) >= 3) {
                    $runCloudItems[] = [
                        'id' => $id,
                        'server_id' => (int) $parts[1],
                        'web_app_id' => (int) $parts[2],
                    ];
                }
            }
        }

        $processedIds = [];
        $skipped = [];

        // 1. Process DB deployments
        if (! empty($dbIds)) {
            $dbResult = $this->lifecycleService->bulkDelete($dbIds);
            $processedIds = array_merge($processedIds, $dbResult['processed_ids'] ?? []);
            $skipped = array_merge($skipped, $dbResult['skipped'] ?? []);
        }

        // 2. Process RunCloud-only apps
        foreach ($runCloudItems as $rcItem) {
            try {
                $this->runCloudService->deleteWebApplication(
                    $rcItem['server_id'],
                    $rcItem['web_app_id']
                );
                $processedIds[] = $rcItem['id'];
            } catch (\Throwable $e) {
                $skipped[] = [
                    'id' => $rcItem['id'],
                    'reason' => 'RunCloud error: '.$e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => 'Bulk deletion completed.',
            'data' => [
                'processed_ids' => $processedIds,
                'skipped' => $skipped,
            ],
        ]);
    }

    /**
     * Display a listing of deployments.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $deployments = Deployment::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return DeploymentResource::collection($deployments);
    }

    /**
     * Store a newly created deployment.
     */
    public function store(StoreDeploymentRequest $request): JsonResponse
    {
        $deployment = $this->lifecycleService->submit($request->user(), $request->validated());
        $isUpload = $deployment->archive_source === 'upload';

        return response()->json([
            'data' => new DeploymentResource($deployment->load('server')),
            'message' => $isUpload
                ? 'Uploaded ZIP accepted and recovery queued.'
                : 'Recovery accepted and waiting for the Wayback ZIP.',
        ], 202);
    }

    /**
     * Display the specified deployment.
     */
    public function show(Deployment $deployment): DeploymentResource
    {
        return new DeploymentResource($deployment);
    }

    /**
     * Remove the specified deployment.
     */
    public function destroy(Deployment $deployment): JsonResponse
    {
        try {
            $this->lifecycleService->delete($deployment);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Deployment deleted.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredAdminQuery(array $filters): Builder
    {
        return Deployment::query()
            ->when(isset($filters['user_id']), fn (Builder $query) => $query->where('user_id', $filters['user_id']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['archive_status']), fn (Builder $query) => $query->where('archive_status', $filters['archive_status']))
            ->when(isset($filters['server_id']), fn (Builder $query) => $query->where('server_id', $filters['server_id']))
            ->when(isset($filters['search']), fn (Builder $query) => $query->where('domain', 'like', '%'.$filters['search'].'%'))
            ->when(isset($filters['current_step']), fn (Builder $query) => $query->where('current_step', $filters['current_step']))
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']));
    }
}
