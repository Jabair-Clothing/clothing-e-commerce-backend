<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Transition\TransitionController;

Route::middleware('auth:api', 'role:admin,stuff,member')->group(function () {


    // Transaction routes
    Route::prefix('transiions')->group(function () {
        Route::get('/', [TransitionController::class, 'index']);
    });
});
