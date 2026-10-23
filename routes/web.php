<?php

use App\Http\Controllers\Web\DashboardController;
use Illuminate\Support\Facades\Route;

// Redirect root to login
Route::get('/', function () {
    return redirect()->route('login');
});

// Guest Page Routes (Loads HTML views)
Route::middleware('guest')->group(function (): void {
    Route::get('/login', function () {
        return view('auth.login');
    })->name('login');
});

// Protected Page Routes (Requires Authentication)
Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [\App\Http\Controllers\Web\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/environment-variables', function () {
        $variables = \App\Models\EnvironmentVariable::with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($env) {
                if ($env->type === 'secret') {
                    $env->value = null;
                } else {
                    $env->makeVisible('value');
                }
                return $env;
            });
        return view('pages.environment-variables', compact('variables'));
    })->name('environment-variables');
    Route::get('/google-search-console', function () {
        return view('pages.google-search-console');
    })->middleware('admin')->name('google-search-console');
    Route::get('/servers', function () {
        $servers = \App\Models\Server::orderBy('created_at', 'desc')->get();
        return view('pages.servers', compact('servers'));
    })->name('servers');
    Route::get('/users', function () {
        $users = \App\Models\User::orderBy('created_at', 'desc')->get();
        return view('pages.users', compact('users'));
    })->name('users');
    Route::get('/deployments/{deployment}/logs', function (\App\Models\Deployment $deployment) {
        $deployment->load(['server', 'user']);
        return view('pages.deployment-logs', compact('deployment'));
    })->name('deployments.logs.page');
    Route::get('/deployments/{deployment}/files', function (\App\Models\Deployment $deployment) {
        $deployment->load(['server', 'user']);
        return view('pages.deployment-files', compact('deployment'));
    })->name('deployments.files.page');
    Route::get('/deployments/{deployment}/files/download', [\App\Http\Controllers\Api\DeploymentFileEditorController::class, 'downloadZip'])->name('deployments.files.download');
    Route::get('/google/redirect', function (\Illuminate\Http\Request $request) {
        return redirect()->away(app(\App\Services\GoogleSearchConsole\GoogleSearchConsoleService::class)
            ->createOAuthUrl((int) $request->user()->getKey()));
    })->middleware('admin')->name('google.redirect');
});

// Google OAuth callback (matches GOOGLE_REDIRECT_URI)
Route::get('/google/callback', [\App\Http\Controllers\Web\GoogleSearchConsoleOAuthController::class, 'handleCallback'])
    ->name('google.callback');

    // Admin-Only Web Pages
    Route::middleware('admin')->group(function (): void {
        Route::get('/environment-variables', function () {
            $variables = \App\Models\EnvironmentVariable::with('user')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($env) {
                    if ($env->type === 'secret') {
                        $env->value = null;
                    } else {
                        $env->makeVisible('value');
                    }
                    return $env;
                });
            return view('pages.environment-variables', compact('variables'));
        })->name('environment-variables');

        Route::get('/servers', function () {
            $servers = \App\Models\Server::orderBy('created_at', 'desc')->get();
            return view('pages.servers', compact('servers'));
        })->name('servers');

        Route::get('/users', function () {
            $users = \App\Models\User::orderBy('created_at', 'desc')->get();
            return view('pages.users', compact('users'));
        })->name('users');
    });

