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
        $variables = \App\Models\EnvironmentVariable::with('user')->orderBy('created_at', 'desc')->get();
        return view('pages.environment-variables', compact('variables'));
    })->name('environment-variables');
});