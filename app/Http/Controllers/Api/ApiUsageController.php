<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiUsageIndexRequest;
use App\Models\Deployment;
use App\Services\ApiUsage\ApiUsageQueryService;
use Illuminate\Http\JsonResponse;

class ApiUsageController extends Controller
{
    public function __construct(
        private ApiUsageQueryService $usageQuery,
    ) {}

    public function index(ApiUsageIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();

        return response()->json([
            'data' => [
                'summary' => $this->usageQuery->summary($filters),
                'operations' => $this->usageQuery->operations($filters),
            ],
        ]);
    }

    public function deployment(ApiUsageIndexRequest $request, Deployment $deployment): JsonResponse
    {
        $filters = [
            ...$request->safe()->except('deployment_id'),
            'deployment_id' => $deployment->getKey(),
        ];

        return response()->json([
            'data' => [
                'deployment_id' => $deployment->getKey(),
                'summary' => $this->usageQuery->summary($filters),
                'operations' => $this->usageQuery->operations($filters),
            ],
        ]);
    }
}
