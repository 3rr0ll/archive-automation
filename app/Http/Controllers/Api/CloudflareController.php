<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Cloudflare\CloudflareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CloudflareController extends Controller
{
    public function __construct(
        protected CloudflareService $cloudflareService
    ) {}

    public function dnsRecords(Request $request): JsonResponse
    {
        $request->validate([
            'domain' => ['required', 'string'],
        ]);

        $records = $this->cloudflareService->getDnsRecords($request->query('domain'));

        return response()->json([
            'data' => $records,
        ]);
    }
}
