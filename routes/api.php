<?php

use App\Http\Controllers\Api\ApiCredentialController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CloudflareController;
use App\Http\Controllers\Api\DeploymentController;
use App\Http\Controllers\Api\RunCloudController;
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
    Route::post('/refresh', [AuthController::class, 'refresh']);

    // RunCloud Empty WebApp Creation API
    Route::get('/runcloud/servers', [RunCloudController::class, 'getServers']);
    Route::post('/runcloud/webapp', [RunCloudController::class, 'createApp']);

    // Cloudflare Workers & DNS Management API
    Route::post('/cloudflare/workers', [CloudflareController::class, 'changeWorkers']);
    Route::post('/cloudflare/workers/change', [CloudflareController::class, 'changeWorkers']);
    Route::post('/cloudflare/worker-routes', [CloudflareController::class, 'changeWorkers']);
    Route::get('/cloudflare/dns', [CloudflareController::class, 'dnsRecords']);
    Route::delete('/cloudflare/dns', [CloudflareController::class, 'deleteAllDns']);
    Route::post('/cloudflare/dns/setup-runcloud', [CloudflareController::class, 'setupRunCloudDns']);
    Route::post('/cloudflare/dns', [CloudflareController::class, 'setupRunCloudDns']);
});

// Admin API Routes (Requires Admin Role)
Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::post('/users/bulk-delete', [UserController::class, 'bulkDestroy']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    Route::put('/users/{user}/password', [UserController::class, 'resetPassword']);

    Route::get('/api-credentials', [ApiCredentialController::class, 'index'])->name('api-credentials.index');
    Route::post('/api-credentials', [ApiCredentialController::class, 'store'])->name('api-credentials.store');
    Route::get('/api-credentials/{apiCredential}', [ApiCredentialController::class, 'show'])->name('api-credentials.show');
    Route::put('/api-credentials/{apiCredential}', [ApiCredentialController::class, 'update'])->name('api-credentials.update');
    Route::delete('/api-credentials/{apiCredential}', [ApiCredentialController::class, 'destroy'])->name('api-credentials.destroy');

    Route::get('/deployments', [DeploymentController::class, 'adminIndex'])->name('deployments.index');
    Route::post('/deployments', [DeploymentController::class, 'store'])->name('deployments.store');
    Route::get('/deployments/{deployment}', [DeploymentController::class, 'adminShow'])->name('deployments.show');
    Route::post('/deployments/{deployment}/cancel', [DeploymentController::class, 'cancel'])->name('deployments.cancel');
    Route::post('/deployments/{deployment}/retry', [DeploymentController::class, 'retry'])->name('deployments.retry');
    Route::delete('/deployments/{deployment}', [DeploymentController::class, 'adminDestroy'])->name('deployments.destroy');
});
