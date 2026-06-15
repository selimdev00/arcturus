<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/organization', [OrganizationController::class, 'show']);
    Route::post('/settings/source', [SettingsController::class, 'store']);
    Route::get('/organization/reviews', [OrganizationController::class, 'reviews']);
    Route::post('/organization/reparse', [OrganizationController::class, 'reparse']);
});
