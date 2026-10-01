<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeploymentController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public API Routes (Auth)
Route::post('/login', [AuthController::class, 'login']);

// Protected API Routes (Requires Sanctum Bearer Token)
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', function (Request $request) {
        return response()->json($request->user());
    });
    Route::post('/logout', [AuthController::class, 'logout']);

    // Deployments API
    Route::get('/deployments', [DeploymentController::class, 'index']);
    Route::post('/deployments', [DeploymentController::class, 'store']);
    Route::get('/deployments/{deployment}', [DeploymentController::class, 'show']);
    Route::delete('/deployments/{deployment}', [DeploymentController::class, 'destroy']);
});

// Admin API Routes (Requires Admin Role)
Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}/password', [UserController::class, 'resetPassword']);
});