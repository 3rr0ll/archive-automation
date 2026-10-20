<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\GoogleSearchConsole\GoogleSearchConsoleOAuthService;
use App\Services\GoogleSearchConsole\GoogleSearchConsoleSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class GoogleSearchConsoleOAuthController extends Controller
{
    public function redirect(Request $request, GoogleSearchConsoleOAuthService $oauth): RedirectResponse
    {
        try {
            return redirect()->away($oauth->authorizationUrl($request));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('google-search-console')
                ->with('google_error', $exception->getMessage());
        }
    }

    public function callback(
        Request $request,
        GoogleSearchConsoleOAuthService $oauth,
        GoogleSearchConsoleSettings $settings,
    ): RedirectResponse {
        try {
            $connection = $oauth->exchangeCode($request);
            $settings->saveConnection(
                $connection['refresh_token'],
                $connection['email'],
                $request->user()?->id,
            );

            return redirect()->route('google-search-console')
                ->with('google_success', 'Google Search Console connected successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('google-search-console')
                ->with('google_error', $exception->getMessage());
        }
    }
}
