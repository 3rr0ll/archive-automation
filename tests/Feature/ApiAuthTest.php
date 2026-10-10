<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_via_api_and_receive_token(): void
    {
        $user = User::factory()->create([
            'email' => 'testapi@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'testapi@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token_type',
                'access_token',
                'user' => ['id', 'name', 'email'],
            ]);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'testapi@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'testapi@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_access_api_user_endpoint(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJsonPath('email', $user->email);
    }

    public function test_user_can_logout_and_revoke_token(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Successfully logged out']);

        $this->assertCount(0, $user->tokens);
    }

    public function test_user_can_refresh_token(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'token_type',
                'access_token',
                'user' => ['id', 'name', 'email'],
            ]);

        // Old token should be deleted and replaced with exactly 1 new active token
        $this->assertCount(1, $user->fresh()->tokens);
    }

    public function test_refresh_validates_and_uses_the_device_name(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('old-device')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/refresh', ['device_name' => 'mobile-app'])
            ->assertOk();

        $this->assertSame('mobile-app', $user->fresh()->tokens()->sole()->name);

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('validation-token')->plainTextToken)
            ->postJson('/api/refresh', ['device_name' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('device_name');
    }

    public function test_user_can_logout_via_session_without_transient_token_error(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Successfully logged out']);

        $this->assertGuest('web');
    }
}
