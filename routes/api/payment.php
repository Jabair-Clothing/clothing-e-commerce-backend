<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Payment\PaymentController;

Route::middleware('auth:sanctum', 'role:admin,stuff,member')->group(function () {
    // payment routes
    Route::prefix('payments')->group(function () {
        Route::put('/update-status/{paymentId}', [PaymentController::class, 'updatePaymentStatus']);
        Route::put('/update-paid-amount/{paymentId}', [PaymentController::class, 'updatePadiAmount']);
    });
});
