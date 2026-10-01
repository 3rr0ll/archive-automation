<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_reset_any_user_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'role' => 'user',
            'password' => 'old-password',
        ]);

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/users/{$user->id}/password", [
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Password reset successfully.',
            ]);

        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
    }

    public function test_non_admin_cannot_reset_user_password(): void
    {
        $actor = User::factory()->create(['role' => 'user']);
        $target = User::factory()->create(['role' => 'user']);

        $token = $actor->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/users/{$target->id}/password", [
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ]);

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ]);
    }

    public function test_guest_cannot_reset_user_password(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->putJson("/api/users/{$user->id}/password", [
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertUnauthorized();
    }

    public function test_admin_password_reset_revokes_user_tokens(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        $user->createToken('existing-session');

        $token = $admin->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/users/{$user->id}/password", [
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ])
            ->assertOk();

        $this->assertCount(0, $user->fresh()->tokens);
    }
}
