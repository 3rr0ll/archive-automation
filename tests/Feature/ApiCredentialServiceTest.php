<?php

namespace Tests\Feature;

use App\Models\ApiCredential;
use App\Models\User;
use App\Services\Credentials\ApiCredentialService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiCredentialServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_maps_plain_request_token_data_to_encrypted_storage(): void
    {
        $user = User::factory()->create();
        $service = app(ApiCredentialService::class);

        $credential = $service->create([
            'user_id' => $user->id,
            'service' => 'cloudflare',
            'account_name' => 'Operations',
            'token_data' => 'secret-token',
        ]);

        $this->assertSame('secret-token', $credential->tokenData());
        $this->assertNotSame('secret-token', $credential->getRawOriginal('token_data_encrypted'));

        $updated = $service->update($credential, [
            'account_name' => 'Updated Operations',
            'token_data' => 'rotated-token',
        ]);

        $this->assertSame('Updated Operations', $updated->account_name);
        $this->assertSame('rotated-token', $updated->tokenData());
    }

    public function test_it_prevents_generic_management_of_internet_archive_settings(): void
    {
        $credential = ApiCredential::factory()->internetArchive()->create();

        $this->expectException(ModelNotFoundException::class);

        app(ApiCredentialService::class)->delete($credential);
    }
}
