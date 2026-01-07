<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Category\CategoryController;

Route::middleware('auth:api', 'role:admin,stuff,member')->group(function () {

    // Parent Categories Routes
    Route::prefix('categories/parents')->group(function () {
        Route::post('/', [CategoryController::class, 'storeParent']);
        Route::get('/', [CategoryController::class, 'indexParents']);
        Route::get('/{id}', [CategoryController::class, 'showParent']);
        Route::post('/{id}', [CategoryController::class, 'updateParent']);
        Route::delete('/{id}', [CategoryController::class, 'destroyParent']);
    });

    // Sub Categories Routes
    Route::prefix('categories')->group(function () {
        Route::post('/', [CategoryController::class, 'storeSubCategory']);
        Route::get('/', [CategoryController::class, 'indexSubCategories']);
        Route::get('/{id}', [CategoryController::class, 'showSubCategory']);
        Route::post('/{id}', [CategoryController::class, 'updateSubCategory']);
        Route::delete('/{id}', [CategoryController::class, 'destroySubCategory']);
    });
});

// Public Routes (if needed)    
Route::get('categories/parents', [CategoryController::class, 'indexParents']);
Route::get('categories/parents/{id}', [CategoryController::class, 'showParent']);
Route::get('categories', [CategoryController::class, 'indexSubCategories']);
