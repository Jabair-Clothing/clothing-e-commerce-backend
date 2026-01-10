<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Product\ProductController;

Route::get('/products/top-selling', [ProductController::class, 'topSelling']);
Route::get('/products/most-viewed', [ProductController::class, 'mostViewed']);

Route::middleware('auth:api', 'role:admin,stuff,member')->group(function () {
    // Product Management Routes
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::patch('/products/{id}/status', [ProductController::class, 'toggleStatus']);
    Route::post('/products/{id}/images', [ProductController::class, 'uploadImage']);
    Route::put('/products/{id}/images/{image_id}', [ProductController::class, 'updateImage']);
    Route::delete('/products/{id}/images/{image_id}', [ProductController::class, 'deleteImage']);
    Route::put('/products/{id}/skus/{sku_id}', [ProductController::class, 'updateSku']);
    Route::post('/products/{id}/skus', [ProductController::class, 'addSku']);
    Route::delete('/products/{id}/sku-data', [ProductController::class, 'deleteSkuData']);
    Route::get('/products/{id}/sku-attributes', [ProductController::class, 'getSkuAttributes']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
});

// Public Routes
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::post('/products/view', [ProductController::class, 'productView']);
