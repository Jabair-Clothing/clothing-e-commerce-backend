<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\UserController;

Route::middleware('auth:sanctum', 'role:admin,stuff,member')->group(function () {

    // Activity routes
    Route::prefix('activitys')->group(function () {
        Route::get('/', [UserController::class, 'getActivities']);
    });
});
