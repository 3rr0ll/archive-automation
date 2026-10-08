<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDeploymentIndexRequest;
use App\Http\Requests\BulkDeploymentRequest;
use App\Http\Requests\DeploymentLogsRequest;
use App\Http\Requests\StoreDeploymentRequest;
use App\Http\Resources\DeploymentResource;
use App\Models\Deployment;
use App\Services\Deployment\DeploymentLifecycleService;
use App\Services\Deployment\DeploymentStatisticsService;
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
        $query = $this->filteredAdminQuery($request->safe()->except([
            'sort_by',
            'sort_direction',
            'per_page',
        ]));

        return response()->json([
            'data' => $this->statisticsService->calculate($query),
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

    public function bulkDestroy(BulkDeploymentRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Bulk deletion completed.',
            'data' => $this->lifecycleService->bulkDelete($request->validated('ids')),
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

        return response()->json([
            'data' => new DeploymentResource($deployment->load('server')),
            'message' => 'Recovery accepted and waiting for the Wayback ZIP.',
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
