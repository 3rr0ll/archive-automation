<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeploymentController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public API Routes (Auth)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Email verification (link from email — includes id, hash, expires, signature)
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed'])
    ->name('verification.verify');

// Protected API Routes (Requires Sanctum Bearer Token)
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', function (Request $request) {
        return response()->json($request->user());
    });
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Deployments API
    Route::get('/deployments', [DeploymentController::class, 'index']);
    Route::post('/deployments', [DeploymentController::class, 'store']);
    Route::get('/deployments/{deployment}', [DeploymentController::class, 'show']);
    Route::delete('/deployments/{deployment}', [DeploymentController::class, 'destroy']);
});

// Admin API Routes (Requires Admin Role)
Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
    Route::post('/users', [UserController::class, 'store']);
});