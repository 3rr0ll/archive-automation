<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApiCredentialRequest;
use App\Http\Requests\UpdateApiCredentialRequest;
use App\Http\Resources\ApiCredentialResource;
use App\Models\ApiCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiCredentialController extends Controller
{
    /**
     * List API credentials (admin only).
     */
    public function index(Request $request): JsonResponse
    {
        $credentials = ApiCredential::query()
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('service'), fn ($query) => $query->where('service', $request->string('service')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

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
        $credential = ApiCredential::create([
            'user_id' => $request->validated('user_id'),
            'service' => $request->validated('service'),
            'account_name' => $request->validated('account_name'),
            'token_data_encrypted' => $request->validated('token_data'),
        ]);

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
        return response()->json([
            'success' => true,
            'data' => (new ApiCredentialResource($apiCredential))->resolve(),
        ]);
    }

    /**
     * Update an API credential (admin only).
     */
    public function update(UpdateApiCredentialRequest $request, ApiCredential $apiCredential): JsonResponse
    {
        $data = $request->safe()->except('token_data');

        if ($request->filled('token_data')) {
            $data['token_data_encrypted'] = $request->validated('token_data');
        }

        $apiCredential->update($data);

        return response()->json([
            'success' => true,
            'message' => 'API credential updated successfully.',
            'data' => (new ApiCredentialResource($apiCredential->fresh()))->resolve(),
        ]);
    }

    /**
     * Delete an API credential (admin only).
     */
    public function destroy(ApiCredential $apiCredential): JsonResponse
    {
        $apiCredential->delete();

        return response()->json([
            'success' => true,
            'message' => 'API credential deleted successfully.',
        ]);
    }
}
