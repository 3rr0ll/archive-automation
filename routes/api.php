<?php

use App\Http\Controllers\Api\ArchiveController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CloudflareController;
use App\Http\Controllers\Api\DeploymentController;
use App\Http\Controllers\Api\EnvironmentVariableController;
use App\Http\Controllers\Api\InternetArchiveSettingsController;
use App\Http\Controllers\Api\RunCloudController;
use App\Http\Controllers\Api\ServerController;
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
    Route::post('/cloudflare/cache/purge', [CloudflareController::class, 'purgeCache']);
});

// Admin API Routes (Requires Admin Role)
Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
    Route::get('/admin/settings/internet-archive', [InternetArchiveSettingsController::class, 'show']);
    Route::put('/admin/settings/internet-archive', [InternetArchiveSettingsController::class, 'update']);
    Route::post('/admin/settings/internet-archive/test', [InternetArchiveSettingsController::class, 'test']);

    Route::get('/servers', [ServerController::class, 'index'])->name('servers.index');
    Route::post('/servers/sync', [ServerController::class, 'sync'])->name('servers.sync');
    Route::get('/servers/{server}', [ServerController::class, 'show'])->name('servers.show');
    Route::put('/servers/{server}', [ServerController::class, 'update'])->name('servers.update');
    Route::delete('/servers/{server}', [ServerController::class, 'destroy'])->name('servers.destroy');
    Route::post('/servers/{server}/archives/unzip', [ArchiveController::class, 'unzip'])
        ->name('servers.archives.unzip');
    Route::patch('/servers/{server}/toggle-active', [ServerController::class, 'toggleActive'])->name('servers.toggle-active');

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::post('/users/bulk-delete', [UserController::class, 'bulkDestroy']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    Route::put('/users/{user}/password', [UserController::class, 'resetPassword']);

    Route::apiResource('environment-variables', EnvironmentVariableController::class);

    Route::get('/deployments', [DeploymentController::class, 'adminIndex'])->name('deployments.index');
    Route::post('/deployments', [DeploymentController::class, 'store'])->name('deployments.store');
    Route::get('/deployments/statistics', [DeploymentController::class, 'statistics'])->name('deployments.statistics');
    Route::post('/deployments/bulk-cancel', [DeploymentController::class, 'bulkCancel'])->name('deployments.bulk-cancel');
    Route::post('/deployments/bulk-retry', [DeploymentController::class, 'bulkRetry'])->name('deployments.bulk-retry');
    Route::post('/deployments/bulk-delete', [DeploymentController::class, 'bulkDestroy'])->name('deployments.bulk-delete');
    Route::get('/deployments/{deployment}', [DeploymentController::class, 'adminShow'])->name('deployments.show');
    Route::get('/deployments/{deployment}/logs', [DeploymentController::class, 'logs'])->name('deployments.logs');
    Route::post('/deployments/{deployment}/cancel', [DeploymentController::class, 'cancel'])->name('deployments.cancel');
    Route::post('/deployments/{deployment}/retry', [DeploymentController::class, 'retry'])->name('deployments.retry');
    Route::delete('/deployments/{deployment}', [DeploymentController::class, 'adminDestroy'])->name('deployments.destroy');
});
