<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Coupon\CouponController;

Route::middleware('auth:api', 'role:admin,stuff,member')->group(function () {

    // Coupon Routes
    Route::prefix('coupons')->group(function () {
        Route::post('/', [CouponController::class, 'store']);
        Route::get('/', [CouponController::class, 'index']);
        Route::patch('/toggle-status/{coupon_id}', [CouponController::class, 'toggleStatus']);
        Route::put('/{coupon_id}', [CouponController::class, 'update']);
        Route::delete('/{coupon_id}', [CouponController::class, 'destroy']);
    });
});

Route::post('/check-coupon', [CouponController::class, 'checkCoupon']);
