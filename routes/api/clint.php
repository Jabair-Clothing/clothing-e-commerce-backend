<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\UserController;
use App\Models\User;

Route::middleware('auth:api', 'role:admin,stuff,member')->group(function () {

    // client routes
    Route::prefix('clints')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/all-info/{user_id}', [UserController::class, 'shwoAllInfo']);
    });
});

Route::middleware('auth:api')->group(function () {

    Route::prefix('users')->group(function () {
        Route::get('/info', [UserController::class, 'loninUserInfo']);
        Route::get('/dashboard', [UserController::class, 'userDashboard']);
    });
});
