<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $providedId = $request->header('X-Request-ID');
        $requestId = is_string($providedId) && Str::isUuid($providedId)
            ? $providedId
            : (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);
        Log::shareContext(['request_id' => $requestId]);

        try {
            $response = $next($request);
            $response->headers->set('X-Request-ID', $requestId);

            return $response;
        } finally {
            Log::withoutContext(['request_id']);
        }
    }
}
