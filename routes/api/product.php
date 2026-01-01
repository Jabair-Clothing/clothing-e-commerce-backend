<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Product\ProductController;


Route::middleware('auth:api', 'role:admin,stuff,member')->group(function () {
    // Product Management Routes
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::patch('/products/{id}/status', [ProductController::class, 'toggleStatus']);
    Route::post('/products/{id}/images', [ProductController::class, 'uploadImage']);
    Route::delete('/products/{id}/images/{image_id}', [ProductController::class, 'deleteImage']);
    Route::get('/products/{id}/sku-attributes', [ProductController::class, 'getSkuAttributes']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
});
