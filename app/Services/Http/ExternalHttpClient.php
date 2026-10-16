<?php

namespace App\Services\Http;

use Illuminate\Http\Client\PendingRequest;

class ExternalHttpClient
{
    public function configure(PendingRequest $request, ?string $provider = null, ?string $operation = null): PendingRequest
    {
        if ($provider !== null) {
            $request->withAttributes(array_filter([
                'api_usage_provider' => $provider,
                'api_usage_operation' => $operation,
            ]));
        }

        if ($this->shouldVerifySsl()) {
            return $request;
        }

        return $request->withoutVerifying();
    }

    public function shouldVerifySsl(): bool
    {
        if (! app()->environment(['local', 'testing'])) {
            return true;
        }

        return (bool) config('services.http.verify_ssl', true);
    }
}
