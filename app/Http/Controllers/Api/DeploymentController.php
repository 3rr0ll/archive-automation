<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeploymentRequest;
use App\Http\Resources\DeploymentResource;
use App\Models\Deployment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeploymentController extends Controller
{
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
            'status' => 'pending',
            'current_step' => 'queued',
        ]);

        return response()->json([
            'data' => new DeploymentResource($deployment),
            'message' => 'Deployment queued.',
        ], 201);
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
