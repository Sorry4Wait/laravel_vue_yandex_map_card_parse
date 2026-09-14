<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\ReviewController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    Route::get('/organization', [OrganizationController::class, 'current']);
    Route::post('/organization', [OrganizationController::class, 'store']);

    Route::get('/organizations/{organization}/parse-status', [OrganizationController::class, 'parseStatus']);
    Route::get('/organizations/{organization}/reviews', [ReviewController::class, 'index']);
});
