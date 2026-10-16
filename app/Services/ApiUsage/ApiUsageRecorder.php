<?php

namespace App\Services\ApiUsage;

use App\Models\ApiUsageRecord;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ApiUsageRecorder
{
    /** @var array<int, float> */
    private array $startedAt = [];

    public function __construct(
        private ApiUsageContext $context,
    ) {}

    public function requestStarted(Request $request): void
    {
        if ($this->provider($request) !== null) {
            $this->startedAt[spl_object_id($request)] = microtime(true);
        }
    }

    public function requestFinished(Request $request, ?int $statusCode): void
    {
        $provider = $this->provider($request);

        if ($provider === null) {
            return;
        }

        $startedAt = $this->startedAt[spl_object_id($request)] ?? null;
        unset($this->startedAt[spl_object_id($request)]);

        $this->record(
            provider: $provider,
            operation: $this->operation($request),
            method: $request->method(),
            endpoint: $request->url(),
            statusCode: $statusCode,
            successful: $statusCode !== null && $statusCode >= 200 && $statusCode < 300,
            durationMs: $startedAt === null ? null : (int) round((microtime(true) - $startedAt) * 1000),
        );
    }

    public function record(
        string $provider,
        string $operation,
        string $method,
        string $endpoint,
        ?int $statusCode,
        bool $successful,
        ?int $durationMs = null,
        ?int $deploymentId = null,
    ): void {
        try {
            ApiUsageRecord::query()->create([
                'deployment_id' => $deploymentId ?? $this->context->deploymentId(),
                'provider' => $provider,
                'operation' => $operation,
                'method' => strtoupper($method),
                'endpoint' => $this->sanitizeEndpoint($endpoint),
                'status_code' => $statusCode,
                'successful' => $successful,
                'duration_ms' => $durationMs,
                'requested_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Unable to persist external API usage.', [
                'provider' => $provider,
                'operation' => $operation,
                'exception' => $exception::class,
            ]);
        }
    }

    private function provider(Request $request): ?string
    {
        $provider = $request->attributes()['api_usage_provider'] ?? null;

        return is_string($provider) && $provider !== '' ? $provider : null;
    }

    private function operation(Request $request): string
    {
        $operation = $request->attributes()['api_usage_operation'] ?? null;

        if (is_string($operation) && $operation !== '') {
            return $operation;
        }

        $path = parse_url($request->url(), PHP_URL_PATH);

        return strtoupper($request->method()).' '.(is_string($path) ? $path : '/');
    }

    private function sanitizeEndpoint(string $endpoint): string
    {
        $parts = parse_url($endpoint);

        if ($parts === false) {
            return strtok($endpoint, '?') ?: $endpoint;
        }

        $scheme = isset($parts['scheme']) ? $parts['scheme'].'://' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '/';

        return $scheme.$host.$port.$path;
    }
}
