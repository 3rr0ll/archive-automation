<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\GoogleSearchConsoleOAuthController;
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
    Route::middleware('admin')->group(function (): void {
        Route::get('/google-search-console/authorize', [GoogleSearchConsoleOAuthController::class, 'redirect'])
            ->name('google-search-console.authorize');
        Route::get('/google-search-console/callback', [GoogleSearchConsoleOAuthController::class, 'callback'])
            ->name('google-search-console.callback');
    });
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
});
