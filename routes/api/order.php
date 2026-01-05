<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Order\OrderController;
use App\Http\Controllers\OrderInfo\OrderInfoController;

Route::post('orders/place-order', [OrderController::class, 'placeOrder']);
Route::middleware('auth:api')->group(function () {
    // Order Routes for admin
    Route::prefix('orders')->group(function () {
        Route::get('/users', [OrderController::class, 'userindex']);
        Route::get('/users/{invoice_code}', [OrderController::class, 'userOrderDetaileShow']);
    });
});


Route::middleware('auth:api', 'role:admin,stuff,member')->group(function () {
    // Order Routes for admin
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'adminindex']);
        Route::get('/{order_Id}', [OrderController::class, 'show']);
        Route::put('/update-status/{order_Id}', [OrderController::class, 'updateStatus']);
        Route::post('/add-product/{orderId}', [OrderController::class, 'addProductToOrder']);
        Route::delete('/products/{order_Id}/remove/{product_Id}', [OrderController::class, 'removeProductFromOrder']);
        Route::put('/products/{order_Id}/update-quantity/{product_Id}', [OrderController::class, 'updateProductQuantity']);
        Route::put('/update-customer-info/{order_Id}', [OrderController::class, 'updateCustomerInfo']);

        Route::delete('{orderId}', [OrderController::class, 'deleteOrder']);

        Route::get('/summary/due-amount', [OrderController::class, 'getOrderSummary']);
    });

    // Order Info Routes
    Route::prefix('orderinfo')->group(function () {
        Route::get('/', [OrderInfoController::class, 'index']);
        Route::put('/', [OrderInfoController::class, 'update']);
    });
});
