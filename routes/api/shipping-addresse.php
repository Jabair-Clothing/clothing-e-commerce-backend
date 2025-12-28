<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Shipping\ShippingController;

Route::middleware('auth:sanctum')->group(function () {
    // Shipping Address Routes
    Route::prefix('shipping-addresses')->group(function () {
        Route::post('/', [ShippingController::class, 'store']);
        Route::get('/{user_id}', [ShippingController::class, 'index']);
        Route::get('/{shipping_id}', [ShippingController::class, 'show']);
        Route::put('/{shipping_id}', [ShippingController::class, 'update']);
        Route::delete('/{shipping_id}', [ShippingController::class, 'destroy']);

        Route::get('/', [ShippingController::class, 'userindex']);
    });
});
