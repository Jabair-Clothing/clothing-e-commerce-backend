<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Stuff\StuffController;


Route::middleware('auth:api', 'role:admin,stuff,member')->group(function () {

    // client routes
    Route::prefix('stuff')->group(function () {
        Route::get('/', [StuffController::class, 'index']);
        // Route::get('/all-info/{user_id}', [StuffController::class, 'shwoAllInfo']);
    });
});
