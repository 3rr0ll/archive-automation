<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateRunCloudWebAppRequest;
use App\Services\RunCloud\RunCloudService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class RunCloudController extends Controller
{
    public function __construct(
        protected RunCloudService $runCloudService
    ) {}

    /**
     * Create an Empty Web Application on RunCloud.
     */
    public function createApp(CreateRunCloudWebAppRequest $request): JsonResponse
    {
        $domain = (string) $request->validated('domain');
        $serverId = $request->validated('server_id') ? (int) $request->validated('server_id') : null;
        $serverIp = $request->validated('server_ip') ? (string) $request->validated('server_ip') : null;

        try {
            $result = $this->runCloudService->createEmptyWebApplication($domain, $serverId, $serverIp);

            if (! empty($result['already_exists'])) {
                return response()->json([
                    'message' => $result['message'],
                    'data' => $result['app'],
                ], 409);
            }

            return response()->json([
                'message' => 'RunCloud Empty Web Application created successfully.',
                'data' => $result,
            ], 201);
        } catch (Exception $e) {
            Log::error('RunCloud web app creation endpoint failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to create RunCloud Web Application.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all servers connected to the RunCloud account.
     */
    public function getServers(): JsonResponse
    {
        try {
            $servers = $this->runCloudService->getServers();

            return response()->json([
                'message' => 'RunCloud servers retrieved successfully.',
                'data' => $servers,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to retrieve RunCloud servers', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to retrieve RunCloud servers.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
