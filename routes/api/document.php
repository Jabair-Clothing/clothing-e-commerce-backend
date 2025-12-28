<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Document\DocumentController;

Route::middleware('auth:sanctum', 'role:admin,stuff,member')->group(function () {
    // Documentation Routes
    Route::prefix('documents')->group(function () {
        Route::put('/{type}', [DocumentController::class, 'updateByType']);
        Route::put('/order-info/{orderinf_id}', [DocumentController::class, 'updateorderInfo']);
    });
});

Route::prefix('documents')->group(function () {
    Route::get('/about', [DocumentController::class, 'showAbout']);
    Route::get('/term-condition', [DocumentController::class, 'showTrueCondition']);
    Route::get('/privacy-policy', [DocumentController::class, 'showPrivacyPolicy']);
    Route::get('/return-policy', [DocumentController::class, 'showReturnPolicy']);
    Route::get('/order-info', [DocumentController::class, 'showOrderInfo']);
});
