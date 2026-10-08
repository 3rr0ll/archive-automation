<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthenticationService
{
    /**
     * @return array{user: User, access_token: string}
     */
    public function authenticate(
        string $email,
        string $password,
        bool $remember = false,
        string $deviceName = 'web-browser',
    ): array {
        if (! Auth::guard('web')->attempt(['email' => $email, 'password' => $password], $remember)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $user = Auth::guard('web')->user();

        if (! $user instanceof User) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        return [
            'user' => $user,
            'access_token' => $this->issueToken($user, $deviceName),
        ];
    }

    public function revokeCurrentToken(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }

    public function rotateToken(User $user, string $deviceName = 'refreshed-token'): string
    {
        return DB::transaction(function () use ($user, $deviceName): string {
            $this->revokeCurrentToken($user);

            return $this->issueToken($user, $deviceName);
        });
    }

    private function issueToken(User $user, string $deviceName): string
    {
        return $user->createToken($deviceName)->plainTextToken;
    }
}
