<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServerRequest;
use App\Http\Requests\UpdateServerRequest;
use App\Http\Resources\ServerResource;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServerController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ServerResource::collection(
            Server::query()->orderBy('name')->get()
        );
    }

    public function store(StoreServerRequest $request): JsonResponse
    {
        $server = Server::create($request->validated());

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

        return response()->json([
            'message' => 'Server deleted successfully.',
        ]);
    }
}
