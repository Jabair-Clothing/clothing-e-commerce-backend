<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Product\ProductController;


Route::middleware('auth:api', 'role:admin,stuff,member')->group(function () {
    // Product Management Routes
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::post('/products/{id}', [ProductController::class, 'update']); 
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::post('/products/{id}/status', [ProductController::class, 'changeStatus']);
});
