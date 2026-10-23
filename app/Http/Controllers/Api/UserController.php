<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDeleteUsersRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\Users\UserAdministrationService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    public function __construct(
        private UserAdministrationService $userAdministrationService,
    ) {}

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
     * Create a regular user (admin only).
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            ...$request->validated(),
            'role' => 'user',
        ]);

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
        try {
            $this->userAdministrationService->delete($request->user(), $user);
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

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
        $result = $this->userAdministrationService->bulkDelete(
            $request->user(),
            $request->validated('ids'),
        );

        if ($result['deleted_count'] === 0 && $result['skipped_self'] && $result['skipped_admin_ids'] === []) {
            return response()->json([
                'success' => false,
                'message' => 'No users deleted. You cannot delete your own account.',
            ], 422);
        }

        if ($result['deleted_count'] === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No users deleted. Admin accounts cannot be deleted.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Users deleted successfully.',
            'data' => [
                'deleted_ids' => $result['deleted_ids'],
                'deleted_count' => $result['deleted_count'],
                'skipped_admin_ids' => $result['skipped_admin_ids'],
            ],
        ]);
    }

    /**
     * Reset a non-admin user's password, or the current admin's own password (admin only).
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        try {
            $this->userAdministrationService->resetPassword(
                $request->user(),
                $user,
                (string) $request->input('password'),
            );
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully.',
        ]);
    }
}
