<?php

namespace App\Services\RunCloud;

use App\Models\EnvironmentVariable;
use App\Services\Cache\ApplicationCache;
use App\Services\Http\ExternalHttpClient;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RunCloudClient
{
    protected string $baseUrl;

    protected ?string $apiKey;

    protected ?string $apiSecret;

    protected ?string $apiToken;

    protected int $timeout;

    protected ApplicationCache $cache;

    protected ExternalHttpClient $httpClient;

    public function __construct(
        ?ApplicationCache $cache = null,
        ?ExternalHttpClient $httpClient = null,
    ) {
        $this->cache = $cache ?? app(ApplicationCache::class);
        $this->httpClient = $httpClient ?? app(ExternalHttpClient::class);
        $this->baseUrl = rtrim((string) Config::get('services.runcloud.base_url', 'https://manage.runcloud.io/api/v3'), '/');
        $this->apiKey = Config::get('services.runcloud.api_key');
        $this->apiSecret = Config::get('services.runcloud.api_secret');
        $this->apiToken = Config::get('services.runcloud.api_token');
        $this->timeout = (int) Config::get('services.runcloud.timeout', 30);
    }

    /**
     * Retrieve an environment variable value from DB first, falling back to Config/.env.
     */
    public function getEnvValue(string $key, mixed $default = null): mixed
    {
        try {
            $record = EnvironmentVariable::where('key', $key)->latest('updated_at')->latest('id')->first();
            if ($record && ! empty($record->value)) {
                return $record->value;
            }
        } catch (Exception) {
            // DB not available or table not migrated yet
        }

        return $default;
    }

    /**
     * Prepared HTTP client with authentication and headers.
     *
     * @throws Exception
     */
    public function client(): PendingRequest
    {
        $apiToken = $this->getEnvValue('RUNCLOUD_API_TOKEN', $this->apiToken);
        $apiKey = $this->getEnvValue('RUNCLOUD_API_KEY', $this->apiKey);
        $apiSecret = $this->getEnvValue('RUNCLOUD_API_SECRET', $this->apiSecret);

        $client = $this->httpClient->configure(
            Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]),
            'runcloud',
        );

        if (! empty($apiToken)) {
            return $client->withToken($apiToken);
        }

        if (! empty($apiKey) && empty($apiSecret)) {
            return $client->withToken($apiKey);
        }

        if (! empty($apiKey) && ! empty($apiSecret)) {
            return $client->withBasicAuth($apiKey, $apiSecret);
        }

        throw new Exception('RunCloud credentials not configured. Please add RUNCLOUD_API_KEY or RUNCLOUD_API_TOKEN in your Environment Variables UI or .env file.');
    }

    /**
     * Handle and normalize API errors without leaking secrets.
     *
     * @throws Exception
     */
    public function handleApiError(string $context, Response $response): void
    {
        $statusCode = $response->status();
        $errorBody = $response->json();
        $message = $errorBody['message'] ?? $errorBody['error'] ?? $response->body();

        Log::error("{$context}: HTTP {$statusCode}", [
            'status' => $statusCode,
            'message' => $message,
        ]);

        throw new Exception("RunCloud API error ({$statusCode}): {$message}");
    }

    public function cache(): ApplicationCache
    {
        return $this->cache;
    }
}
