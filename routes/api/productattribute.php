<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Product\ProductAttributeController;

Route::middleware('auth:api', 'role:admin,stuff,member')->group(function () {
    // Attributes Routes
    Route::prefix('attributes')->group(function () {
        Route::get('/', [ProductAttributeController::class, 'index']);
        Route::post('/', [ProductAttributeController::class, 'store']);
        Route::get('/{id}', [ProductAttributeController::class, 'show']);
        Route::put('/{id}', [ProductAttributeController::class, 'update']);
        Route::delete('/{id}', [ProductAttributeController::class, 'destroy']);
    });
});
