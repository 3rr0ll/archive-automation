<?php

use App\Modules\Auth\Controllers\ApiAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public API Routes
Route::post('/login', [ApiAuthController::class, 'login']);

// Protected API Routes (Requires Sanctum Bearer Token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json($request->user());
    });
    Route::post('/logout', [ApiAuthController::class, 'logout']);
});
