<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class RequestLoggingContextTest extends TestCase
{
    public function test_responses_include_a_generated_request_id(): void
    {
        $this->get('/up')
            ->assertOk()
            ->assertHeader('X-Request-ID');
    }

    public function test_valid_incoming_request_ids_are_preserved(): void
    {
        $requestId = (string) Str::uuid();

        $this->withHeader('X-Request-ID', $requestId)
            ->get('/up')
            ->assertOk()
            ->assertHeader('X-Request-ID', $requestId);
    }
}
