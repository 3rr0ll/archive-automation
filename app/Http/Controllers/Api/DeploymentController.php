<?php

namespace App\Http\Controllers\Api;

use App\Enums\ArchiveStatus;
use App\Enums\DeploymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeploymentRequest;
use App\Http\Resources\DeploymentResource;
use App\Jobs\DeployArchivedWebsite;
use App\Jobs\WaitForWaybackArchive;
use App\Models\Deployment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeploymentController extends Controller
{
    /**
     * Display deployments across all users for administrators.
     */
    public function adminIndex(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', array_column(DeploymentStatus::cases(), 'value'))],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $deployments = Deployment::query()
            ->with(['user:id,name,email', 'server'])
            ->when(isset($validated['user_id']), fn ($query) => $query->where('user_id', $validated['user_id']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->latest()
            ->paginate($validated['per_page'] ?? 15);

        return DeploymentResource::collection($deployments);
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

    public function cancel(Deployment $deployment): JsonResponse
    {
        if (! in_array($deployment->status, [DeploymentStatus::Pending, DeploymentStatus::Processing], true)) {
            return response()->json([
                'message' => 'Only pending or processing deployments can be cancelled.',
            ], 422);
        }

        $deployment->update([
            'status' => DeploymentStatus::Cancelled,
            'current_step' => 'cancelled',
        ]);
        $deployment->logs()->create([
            'step_name' => 'cancelled',
            'status' => 'warning',
            'message' => 'Deployment cancelled by an administrator.',
        ]);

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
        } else {
            DeployArchivedWebsite::dispatch($deployment);
        }

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
}
