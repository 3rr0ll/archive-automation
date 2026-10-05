<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeCloudflareWorkersRequest;
use App\Http\Requests\SetupCloudflareRunCloudDnsRequest;
use App\Services\Cloudflare\CloudflareService;
use App\Services\RunCloud\RunCloudService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CloudflareController extends Controller
{
    public function __construct(
        protected CloudflareService $cloudflareService
    ) {}

    /**
     * Change Cloudflare Worker routes for a domain:
     * - www version:
     *     www.domain.com/* -> all-scripts-worker-www
     *     domain.com/*     -> domain-redirect-root-to-www
     * - nonwww version:
     *     domain.com/*     -> all-scripts-worker-root
     *     www.domain.com/* -> domain-redirect-www-to-root
     */
    public function changeWorkers(ChangeCloudflareWorkersRequest $request): JsonResponse
    {
        $domain = (string) $request->validated('domain');
        $version = $request->validated('version');

        try {
            $result = $this->cloudflareService->changeWorkers($domain, $version);

            return response()->json([
                'message' => 'Cloudflare worker routes updated successfully.',
                'data' => $result,
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to change Cloudflare workers', [
                'domain' => $domain,
                'version' => $version,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to change Cloudflare workers.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Configure RunCloud server DNS on Cloudflare:
     * First deletes all DNS records, then adds @ and www A records pointing to RunCloud server.
     */
    public function setupRunCloudDns(
        SetupCloudflareRunCloudDnsRequest $request,
        RunCloudService $runCloudService
    ): JsonResponse {
        $domain = (string) $request->validated('domain');
        $serverId = $request->validated('server_id') ? (int) $request->validated('server_id') : null;
        $serverIp = $request->validated('server_ip');
        $proxied = $request->has('proxied') ? (bool) $request->validated('proxied') : true;

        try {
            if (empty($serverIp)) {
                $serverIp = $runCloudService->getServerIp($serverId);
            }

            $result = $this->cloudflareService->setupRunCloudDns($domain, $serverIp, $proxied);

            return response()->json([
                'message' => 'Cloudflare DNS configured for RunCloud server successfully.',
                'data' => $result,
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to configure Cloudflare DNS for RunCloud server', [
                'domain' => $domain,
                'server_id' => $serverId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to configure Cloudflare DNS for RunCloud.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get DNS records for a domain.
     */
    public function dnsRecords(Request $request): JsonResponse
    {
        $request->validate([
            'domain' => ['required', 'string'],
        ]);

        $domain = (string) $request->input('domain');

        try {
            $records = $this->cloudflareService->getDnsRecords($domain);

            return response()->json([
                'message' => 'Cloudflare DNS records retrieved successfully.',
                'data' => $records,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to retrieve Cloudflare DNS records', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to retrieve Cloudflare DNS records.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete all DNS records for a domain.
     */
    public function deleteAllDns(Request $request): JsonResponse
    {
        $request->validate([
            'domain' => ['required', 'string'],
        ]);

        $domain = (string) $request->input('domain');

        try {
            $result = $this->cloudflareService->deleteAllDnsRecords($domain);

            return response()->json([
                'message' => 'All Cloudflare DNS records deleted successfully.',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete Cloudflare DNS records', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to delete Cloudflare DNS records.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
