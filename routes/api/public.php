<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Contact\ContactController;

// those are zantech landing page apis
Route::middleware('auth:api', 'role:admin,stuff,member')->group(function () {

    // Contact Us
    Route::prefix('contact')->group(function () {
        Route::get('/', [ContactController::class, 'index']);
        Route::delete('/{contact_id}', [ContactController::class, 'destroy']);
    });
});
