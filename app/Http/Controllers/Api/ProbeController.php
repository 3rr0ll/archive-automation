<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Prober\DnsProbeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProbeController extends Controller
{
    public function __construct(
        protected DnsProbeService $dnsProbeService
    ) {}

    /**
     * POST /api/probe
     * Probe multiple IPs and protocols for a given domain.
     */
    public function probe(Request $request): JsonResponse
    {
        // Prevent PHP request timeout for large multi-probe sweeps
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $inputIps = $request->input('ips');
        if (is_string($inputIps)) {
            // Support raw pasted text containing IPs
            preg_match_all('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $inputIps, $matches);
            $request->merge(['ips' => array_values(array_unique($matches[0] ?? []))]);
        } elseif (is_array($inputIps)) {
            // Clean up any strings in array that might contain tabs/spaces/extra columns
            $cleanedIps = [];
            foreach ($inputIps as $item) {
                if (is_string($item) && preg_match('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $item, $m)) {
                    $cleanedIps[] = $m[0];
                }
            }
            if (! empty($cleanedIps)) {
                $request->merge(['ips' => array_values(array_unique($cleanedIps))]);
            }
        }

        $validator = Validator::make($request->all(), [
            'domain' => ['required', 'string', 'max:253'],
            'ips' => ['required', 'array', 'min:1'],
            'ips.*' => ['required', 'ip'],
            'protocols' => ['sometimes', 'array'],
            'protocols.*' => ['in:http,https'],
            'timeout' => ['sometimes', 'integer', 'min:1', 'max:60'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $domain = $this->cleanDomain((string) $validated['domain']);
        $ips = (array) $validated['ips'];
        $protocols = (array) ($validated['protocols'] ?? ['http', 'https']);
        $timeout = (int) ($validated['timeout'] ?? 15);

        $response = $this->dnsProbeService->probeMultiple($domain, $ips, $protocols, $timeout);

        return response()->json($response);
    }

    /**
     * POST /api/probe/single
     * Probe a single candidate IP and protocol for a given domain.
     */
    public function single(Request $request): JsonResponse
    {
        $inputIp = $request->input('ip');
        if (is_string($inputIp) && preg_match('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $inputIp, $m)) {
            $request->merge(['ip' => $m[0]]);
        }

        $validator = Validator::make($request->all(), [
            'domain' => ['required', 'string', 'max:253'],
            'ip' => ['required', 'ip'],
            'protocol' => ['sometimes', 'in:http,https'],
            'timeout' => ['sometimes', 'integer', 'min:1', 'max:60'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $domain = $this->cleanDomain((string) $validated['domain']);
        $ip = (string) $validated['ip'];
        $protocol = (string) ($validated['protocol'] ?? 'https');
        $timeout = (int) ($validated['timeout'] ?? 15);

        $response = $this->dnsProbeService->probeSingle($domain, $ip, $protocol, $timeout);

        return response()->json($response);
    }

    /**
     * Normalize domain string removing trailing slash or schemes if entered.
     */
    protected function cleanDomain(string $domain): string
    {
        $domain = trim($domain);
        $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
        $domain = explode('/', $domain)[0];

        return strtolower(trim($domain));
    }
}
