<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeploymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public API Routes
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
