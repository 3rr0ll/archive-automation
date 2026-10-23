<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\GoogleSearchConsole\GoogleSearchConsoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleSearchConsoleOAuthController extends Controller
{
    public function __construct(
        private GoogleSearchConsoleService $searchConsoleService,
    ) {}

    /**
     * Handle the OAuth redirect callback from Google.
     */
    public function handleCallback(Request $request)
    {
        if ($request->has('error')) {
            $error = (string) $request->query('error_description', $request->query('error'));
            Log::error('Google OAuth callback error', ['error' => $error]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google authorization denied: ' . $error,
                ], 400);
            }

            return response()->view('pages.google-oauth-complete', [
                'success' => false,
                'message' => 'Google authorization failed: ' . $error,
            ], 400);
        }

        $code = (string) $request->query('code');
        if (blank($code)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing authorization code in callback query parameter.',
                ], 400);
            }

            return response()->view('pages.google-oauth-complete', [
                'success' => false,
                'message' => 'Missing authorization code from Google.',
            ], 400);
        }

        try {
            $state = $request->query('state');
            $account = $this->searchConsoleService->handleOAuthCallback(
                $code,
                $request->user()?->id,
                is_string($state) ? $state : null,
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Google Search Console account linked and activated successfully.',
                    'data' => [
                        'id' => $account->id,
                        'name' => $account->name,
                        'client_email' => $account->client_email,
                        'is_active' => $account->is_active,
                    ],
                ]);
            }

            return response()->view('pages.google-oauth-complete', [
                'success' => true,
                'message' => "Google Search Console account {$account->client_email} connected successfully!",
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'client_email' => $account->client_email,
                    'owner_email' => $account->owner_email,
                    'is_active' => (bool) $account->is_active,
                    'has_credentials' => true,
                    'created_at' => $account->created_at?->toIso8601String(),
                    'updated_at' => $account->updated_at?->toIso8601String(),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Google OAuth callback exchange failed', [
                'error' => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to link Google account: ' . $e->getMessage(),
                ], 500);
            }

            return response()->view('pages.google-oauth-complete', [
                'success' => false,
                'message' => 'Failed to link Google account: ' . $e->getMessage(),
            ], 500);
        }
    }
}
