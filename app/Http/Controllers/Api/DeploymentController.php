<?php

namespace App\Http\Controllers\Api;

use App\Enums\ArchiveStatus;
use App\Enums\DeploymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDeploymentIndexRequest;
use App\Http\Requests\BulkDeploymentRequest;
use App\Http\Requests\DeploymentLogsRequest;
use App\Http\Requests\StoreDeploymentRequest;
use App\Http\Resources\DeploymentResource;
use App\Jobs\DeployArchivedWebsite;
use App\Jobs\WaitForWaybackArchive;
use App\Models\Deployment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeploymentController extends Controller
{
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
        $statusCounts = array_fill_keys(array_column(DeploymentStatus::cases(), 'value'), 0);
        $archiveStatusCounts = array_fill_keys(array_column(ArchiveStatus::cases(), 'value'), 0);

        foreach ((clone $query)->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status') as $status => $count) {
            $statusCounts[$status] = (int) $count;
        }

        foreach ((clone $query)->selectRaw('archive_status, COUNT(*) as aggregate')->groupBy('archive_status')->pluck('aggregate', 'archive_status') as $status => $count) {
            if ($status !== null) {
                $archiveStatusCounts[$status] = (int) $count;
            }
        }

        $stepCounts = (clone $query)
            ->selectRaw('current_step, COUNT(*) as aggregate')
            ->whereNotNull('current_step')
            ->groupBy('current_step')
            ->pluck('aggregate', 'current_step')
            ->map(fn ($count): int => (int) $count)
            ->all();

        return response()->json([
            'data' => [
                'total' => array_sum($statusCounts),
                'active' => $statusCounts[DeploymentStatus::Pending->value]
                    + $statusCounts[DeploymentStatus::Processing->value],
                'status_counts' => $statusCounts,
                'archive_status_counts' => $archiveStatusCounts,
                'current_step_counts' => $stepCounts,
            ],
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
        if (! in_array($deployment->status, [DeploymentStatus::Pending, DeploymentStatus::Processing], true)) {
            return response()->json([
                'message' => 'Only pending or processing deployments can be cancelled.',
            ], 422);
        }

        $this->markCancelled($deployment);

        return response()->json([
            'data' => new DeploymentResource($deployment->fresh()->load('user:id,name,email', 'logs')),
            'message' => 'Deployment cancelled.',
        ]);
    }

    public function retry(Deployment $deployment): JsonResponse
    {
        if (! in_array($deployment->status, [DeploymentStatus::Failed, DeploymentStatus::Cancelled], true)) {
            return response()->json([
                'message' => 'Only failed or cancelled deployments can be retried.',
            ], 422);
        }

        $this->queueRetry($deployment);

        return response()->json([
            'data' => new DeploymentResource($deployment->fresh()->load('user:id,name,email', 'logs')),
            'message' => 'Deployment retry queued.',
        ], 202);
    }

    public function adminDestroy(Deployment $deployment): JsonResponse
    {
        if ($deployment->status === DeploymentStatus::Processing) {
            return response()->json([
                'message' => 'Cancel a processing deployment before deleting it.',
            ], 422);
        }

        $deployment->delete();

        return response()->json([
            'message' => 'Deployment deleted.',
        ]);
    }

    public function bulkCancel(BulkDeploymentRequest $request): JsonResponse
    {
        $deployments = Deployment::query()->whereKey($request->validated('ids'))->get()->keyBy('id');
        $processedIds = [];
        $skipped = [];

        foreach ($request->validated('ids') as $id) {
            $deployment = $deployments->get($id);

            if (! $deployment instanceof Deployment) {
                $skipped[] = ['id' => $id, 'reason' => 'Deployment no longer exists.'];

                continue;
            }

            if (! in_array($deployment->status, [DeploymentStatus::Pending, DeploymentStatus::Processing], true)) {
                $skipped[] = ['id' => $id, 'reason' => 'Only pending or processing deployments can be cancelled.'];

                continue;
            }

            $this->markCancelled($deployment);
            $processedIds[] = $id;
        }

        return response()->json([
            'message' => 'Bulk cancellation completed.',
            'data' => ['processed_ids' => $processedIds, 'skipped' => $skipped],
        ]);
    }

    public function bulkRetry(BulkDeploymentRequest $request): JsonResponse
    {
        $deployments = Deployment::query()->whereKey($request->validated('ids'))->get()->keyBy('id');
        $processedIds = [];
        $skipped = [];

        foreach ($request->validated('ids') as $id) {
            $deployment = $deployments->get($id);

            if (! $deployment instanceof Deployment) {
                $skipped[] = ['id' => $id, 'reason' => 'Deployment no longer exists.'];

                continue;
            }

            if (! in_array($deployment->status, [DeploymentStatus::Failed, DeploymentStatus::Cancelled], true)) {
                $skipped[] = ['id' => $id, 'reason' => 'Only failed or cancelled deployments can be retried.'];

                continue;
            }

            $this->queueRetry($deployment);
            $processedIds[] = $id;
        }

        return response()->json([
            'message' => 'Bulk retry completed.',
            'data' => ['processed_ids' => $processedIds, 'skipped' => $skipped],
        ], 202);
    }

    public function bulkDestroy(BulkDeploymentRequest $request): JsonResponse
    {
        $deployments = Deployment::query()->whereKey($request->validated('ids'))->get()->keyBy('id');
        $processedIds = [];
        $skipped = [];

        foreach ($request->validated('ids') as $id) {
            $deployment = $deployments->get($id);

            if (! $deployment instanceof Deployment) {
                $skipped[] = ['id' => $id, 'reason' => 'Deployment no longer exists.'];

                continue;
            }

            if ($deployment->status === DeploymentStatus::Processing) {
                $skipped[] = ['id' => $id, 'reason' => 'Cancel a processing deployment before deleting it.'];

                continue;
            }

            $deployment->delete();
            $processedIds[] = $id;
        }

        return response()->json([
            'message' => 'Bulk deletion completed.',
            'data' => ['processed_ids' => $processedIds, 'skipped' => $skipped],
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
        $deployment = Deployment::create([
            'user_id' => $request->user()->id,
            'server_id' => $request->validated('server_id'),
            'domain' => $request->validated('domain'),
            'version' => $request->validated('version'),
            'archive_url' => $request->validated('archive_url'),
            'archive_status' => ArchiveStatus::Waiting,
            'status' => DeploymentStatus::Pending,
            'current_step' => 'waiting_for_archive',
        ]);

        $deployment->logs()->create([
            'step_name' => 'waiting_for_archive',
            'status' => 'in_progress',
            'message' => 'Recovery submitted. Waiting for the Wayback ZIP to become available.',
        ]);

        WaitForWaybackArchive::dispatch($deployment);

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
        $deployment->delete();

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

    private function markCancelled(Deployment $deployment): void
    {
        $deployment->update([
            'status' => DeploymentStatus::Cancelled,
            'current_step' => 'cancelled',
        ]);
        $deployment->logs()->create([
            'step_name' => 'cancelled',
            'status' => 'warning',
            'message' => 'Deployment cancelled by an administrator.',
        ]);
    }

    private function queueRetry(Deployment $deployment): void
    {
        $waitsForArchive = filled($deployment->archive_url);

        $deployment->update([
            'archive_status' => $waitsForArchive ? ArchiveStatus::Waiting : $deployment->archive_status,
            'status' => DeploymentStatus::Pending,
            'current_step' => $waitsForArchive ? 'waiting_for_archive' : 'queued',
        ]);
        $deployment->logs()->create([
            'step_name' => $waitsForArchive ? 'waiting_for_archive' : 'queued',
            'status' => 'in_progress',
            'message' => $waitsForArchive
                ? 'Recovery retry queued. Waiting for the Wayback ZIP.'
                : 'Deployment retry queued by an administrator.',
        ]);

        if ($waitsForArchive) {
            WaitForWaybackArchive::dispatch($deployment);

            return;
        }

        DeployArchivedWebsite::dispatch($deployment);
    }
}
