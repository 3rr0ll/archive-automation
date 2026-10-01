<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RunCloud\RunCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RunCloudController extends Controller
{
    public function __construct(
        protected RunCloudService $runCloudService
    ) {}

    public function createApp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'server_id' => ['required', 'integer'],
            'domain' => ['required', 'string'],
        ]);

        $result = $this->runCloudService->createWebApplication($validated['server_id'], $validated['domain']);

        return response()->json([
            'data' => $result,
            'message' => 'Web application created.',
        ]);
    }
}
