<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDeleteUsersRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    /**
     * List all users (admin only).
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Create a user (admin only). Only one admin account is allowed.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data' => $user,
        ], 201);
    }

    /**
     * Show a single user (admin only).
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * Update a non-admin user, or the current admin's own profile (admin only).
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => $user->fresh(),
        ]);
    }

    /**
     * Delete a single non-admin user account (admin only).
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->is($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        if ($user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete another admin account.',
            ], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.',
        ]);
    }

    /**
     * Bulk delete non-admin user accounts (admin only).
     */
    public function bulkDestroy(BulkDeleteUsersRequest $request): JsonResponse
    {
        $actorId = $request->user()->id;

        $ids = collect($request->validated('ids'))
            ->reject(fn (int $id): bool => $id === $actorId)
            ->values();

        if ($ids->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No users deleted. You cannot delete your own account.',
            ], 422);
        }

        $users = User::query()->whereIn('id', $ids)->get();
        $deletable = $users->reject(fn (User $user): bool => $user->isAdmin())->values();

        if ($deletable->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No users deleted. Admin accounts cannot be deleted.',
            ], 422);
        }

        $deletedIds = [];

        foreach ($deletable as $user) {
            $user->tokens()->delete();
            $user->delete();
            $deletedIds[] = $user->id;
        }

        return response()->json([
            'success' => true,
            'message' => 'Users deleted successfully.',
            'data' => [
                'deleted_ids' => $deletedIds,
                'deleted_count' => count($deletedIds),
                'skipped_admin_ids' => $users
                    ->filter(fn (User $user): bool => $user->isAdmin())
                    ->pluck('id')
                    ->values()
                    ->all(),
            ],
        ]);
    }

    /**
     * Reset a non-admin user's password, or the current admin's own password (admin only).
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        if ($user->isAdmin() && ! $request->user()->is($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot reset another admin account password.',
            ], 422);
        }

        $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->update([
            'password' => $request->password,
        ]);

        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully.',
        ]);
    }
}
