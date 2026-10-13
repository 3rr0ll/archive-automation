<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            Log::channel('security')->warning('authentication.failed', [
                'email_hash' => hash('sha256', strtolower(trim($email))),
                'ip' => request()->ip(),
                'device_name' => $deviceName,
            ]);

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

        Log::channel('security')->info('authentication.succeeded', [
            'user_id' => $user->id,
            'ip' => request()->ip(),
            'device_name' => $deviceName,
        ]);

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
            Log::channel('security')->info('authentication.token-revoked', [
                'user_id' => $user->id,
                'token_id' => $token->getKey(),
            ]);
        }
    }

    public function rotateToken(User $user, string $deviceName = 'refreshed-token'): string
    {
        $token = DB::transaction(function () use ($user, $deviceName): string {
            $this->revokeCurrentToken($user);

            return $this->issueToken($user, $deviceName);
        });

        Log::channel('security')->info('authentication.token-rotated', [
            'user_id' => $user->id,
            'device_name' => $deviceName,
        ]);

        return $token;
    }

    private function issueToken(User $user, string $deviceName): string
    {
        return $user->createToken($deviceName)->plainTextToken;
    }
}
