<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        return [$admin, $token];
    }

    public function test_admin_can_list_all_users_including_admins(): void
    {
        [$admin, $token] = $this->actingAsAdmin();
        User::factory()->create(['role' => 'user']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/users');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_admin_can_show_any_user(): void
    {
        [, $token] = $this->actingAsAdmin();
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/users/{$user->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'user');
    }

    public function test_admin_can_create_user(): void
    {
        [, $token] = $this->actingAsAdmin();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/users', [
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'user',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'newuser@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'role' => 'user',
        ]);
    }

    public function test_admin_cannot_create_another_admin(): void
    {
        [, $token] = $this->actingAsAdmin();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/users', [
                'name' => 'Second Admin',
                'email' => 'second-admin@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'admin',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);

        $this->assertDatabaseMissing('users', [
            'email' => 'second-admin@example.com',
        ]);
    }

    public function test_admin_can_update_a_regular_user(): void
    {
        [, $token] = $this->actingAsAdmin();
        $user = User::factory()->create([
            'role' => 'user',
            'name' => 'Old Name',
            'email' => 'user@example.com',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/users/{$user->id}", [
                'name' => 'Updated User',
                'email' => 'updated-user@example.com',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated User');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'updated-user@example.com',
            'role' => 'user',
        ]);
    }

    public function test_admin_cannot_update_another_admin_account(): void
    {
        [, $token] = $this->actingAsAdmin();
        $otherAdmin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Other Admin',
            'email' => 'other-admin@example.com',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/users/{$otherAdmin->id}", [
                'name' => 'Hacked Admin',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user']);

        $this->assertSame('Other Admin', $otherAdmin->fresh()->name);
    }

    public function test_admin_cannot_promote_user_to_admin(): void
    {
        [, $token] = $this->actingAsAdmin();
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/users/{$user->id}", [
                'role' => 'admin',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);

        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_admin_cannot_remove_own_admin_role(): void
    {
        [$admin, $token] = $this->actingAsAdmin();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/users/{$admin->id}", [
                'role' => 'user',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_admin_can_delete_a_regular_user(): void
    {
        [, $token] = $this->actingAsAdmin();
        $target = User::factory()->create(['role' => 'user']);
        $target->createToken('session');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/users/{$target->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'User deleted successfully.',
            ]);

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_delete_another_admin_account(): void
    {
        [, $token] = $this->actingAsAdmin();
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/users/{$otherAdmin->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'You cannot delete another admin account.',
            ]);

        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id]);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        [$admin, $token] = $this->actingAsAdmin();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/users/{$admin->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'You cannot delete your own account.',
            ]);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_bulk_delete_skips_admin_accounts(): void
    {
        [$admin, $token] = $this->actingAsAdmin();
        $user = User::factory()->create(['role' => 'user']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/users/bulk-delete', [
                'ids' => [$user->id, $otherAdmin->id, $admin->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.deleted_count', 1)
            ->assertJsonPath('data.skipped_admin_ids', [$otherAdmin->id]);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_cannot_reset_another_admin_password(): void
    {
        [, $token] = $this->actingAsAdmin();
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/users/{$otherAdmin->id}/password", [
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'You cannot reset another admin account password.',
            ]);
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/users')
            ->assertForbidden();
    }
}
