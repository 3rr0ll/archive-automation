<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\AuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuthenticationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_authenticates_a_user_and_issues_a_named_token(): void
    {
        $user = User::factory()->create([
            'email' => 'service-login@example.com',
            'password' => 'password123',
        ]);

        $result = app(AuthenticationService::class)->authenticate(
            'service-login@example.com',
            'password123',
            false,
            'service-test',
        );

        $this->assertTrue($result['user']->is($user));
        $this->assertNotEmpty($result['access_token']);
        $this->assertTrue(Auth::guard('web')->check());
        $this->assertSame('service-test', $user->tokens()->sole()->name);
    }

    public function test_it_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'service-login@example.com',
            'password' => 'password123',
        ]);

        $this->expectException(ValidationException::class);

        app(AuthenticationService::class)->authenticate(
            'service-login@example.com',
            'invalid-password',
        );
    }

    public function test_it_rotates_the_current_personal_access_token(): void
    {
        $user = User::factory()->create();
        $oldToken = $user->createToken('old-token');
        $user->withAccessToken($oldToken->accessToken);

        $plainTextToken = app(AuthenticationService::class)->rotateToken($user, 'new-token');

        $this->assertNotEmpty($plainTextToken);
        $this->assertCount(1, $user->fresh()->tokens);
        $this->assertSame('new-token', $user->fresh()->tokens()->sole()->name);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldToken->accessToken->id]);
    }
}
