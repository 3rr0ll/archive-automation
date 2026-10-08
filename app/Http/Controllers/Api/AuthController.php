<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RefreshTokenRequest;
use App\Models\User;
use App\Services\Auth\AuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(
        private AuthenticationService $authenticationService,
    ) {}

    /**
     * Handle an incoming authentication request for API.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $authenticated = $this->authenticationService->authenticate(
            $request->validated('email'),
            $request->validated('password'),
            $request->boolean('remember'),
            $request->validated('device_name') ?? 'web-browser',
        );

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return $this->tokenResponse(
            $authenticated['user'],
            $authenticated['access_token'],
            'Login successful',
        );
    }

    /**
     * Destroy the authenticated user's current token & session (Logout).
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->authenticationService->revokeCurrentToken($user);
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
        ]);
    }

    /**
     * Refresh the authenticated user's token by rotating it.
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $token = $this->authenticationService->rotateToken(
            $user,
            $request->validated('device_name') ?? 'refreshed-token',
        );

        return $this->tokenResponse($user, $token, 'Token refreshed successfully');
    }

    private function tokenResponse(User $user, string $token, string $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'token_type' => 'Bearer',
            'access_token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
