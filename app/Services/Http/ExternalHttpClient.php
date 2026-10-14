<?php

namespace App\Services\Http;

use Illuminate\Http\Client\PendingRequest;

class ExternalHttpClient
{
    public function configure(PendingRequest $request): PendingRequest
    {
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
