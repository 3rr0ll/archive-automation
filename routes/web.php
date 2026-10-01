<?php

use App\Http\Controllers\Web\DashboardController;
use Illuminate\Support\Facades\Route;

// Redirect root to login
Route::get('/', function () {
    return redirect()->route('login');
});

// Guest Page Routes
Route::middleware('guest')->group(function (): void {
    Route::get('/login', function () {
        return view('auth.login');
    })->name('login');
});

// Protected Page Routes
Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});