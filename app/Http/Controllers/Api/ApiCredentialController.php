<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexApiCredentialRequest;
use App\Http\Requests\StoreApiCredentialRequest;
use App\Http\Requests\UpdateApiCredentialRequest;
use App\Http\Resources\ApiCredentialResource;
use App\Models\ApiCredential;
use App\Services\Credentials\ApiCredentialService;
use Illuminate\Http\JsonResponse;

class ApiCredentialController extends Controller
{
    public function __construct(
        private ApiCredentialService $credentialService,
    ) {}

    /**
     * List API credentials (admin only).
     */
    public function index(IndexApiCredentialRequest $request): JsonResponse
    {
        $credentials = $this->credentialService->paginate($request->validated());

        return response()->json([
            'success' => true,
            'data' => ApiCredentialResource::collection($credentials->items())->resolve(),
            'meta' => [
                'current_page' => $credentials->currentPage(),
                'last_page' => $credentials->lastPage(),
                'per_page' => $credentials->perPage(),
                'total' => $credentials->total(),
            ],
        ]);
    }

    /**
     * Store a new API credential (admin only).
     */
    public function store(StoreApiCredentialRequest $request): JsonResponse
    {
        $credential = $this->credentialService->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'API credential created successfully.',
            'data' => (new ApiCredentialResource($credential))->resolve(),
        ], 201);
    }

    /**
     * Show a single API credential (admin only).
     */
    public function show(ApiCredential $apiCredential): JsonResponse
    {
        $credential = $this->credentialService->accessible($apiCredential);

        return response()->json([
            'success' => true,
            'data' => (new ApiCredentialResource($credential))->resolve(),
        ]);
    }

    /**
     * Update an API credential (admin only).
     */
    public function update(UpdateApiCredentialRequest $request, ApiCredential $apiCredential): JsonResponse
    {
        $credential = $this->credentialService->update($apiCredential, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'API credential updated successfully.',
            'data' => (new ApiCredentialResource($credential))->resolve(),
        ]);
    }

    /**
     * Delete an API credential (admin only).
     */
    public function destroy(ApiCredential $apiCredential): JsonResponse
    {
        $this->credentialService->delete($apiCredential);

        return response()->json([
            'success' => true,
            'message' => 'API credential deleted successfully.',
        ]);
    }
}
