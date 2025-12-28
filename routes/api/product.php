<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Category\CategoryController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\Tag\TagController;
use App\Http\Controllers\File\FileController;
use App\Http\Controllers\BundleItem\BundleItemController;
use App\Http\Controllers\Product\PublicProductController;


// Public Routes
Route::prefix('products')->group(function () {
    Route::get('/', [PublicProductController::class, 'index']);
    Route::get('/best-selling', [PublicProductController::class, 'bestSellingProducts']);
    Route::get('/new', [PublicProductController::class, 'newProducts']);
    Route::get('/slug/{slug}', [PublicProductController::class, 'showSingleProductBySlug']);
    Route::get('/category/{slug}', [PublicProductController::class, 'shwoProductCategorySlug']);
});


Route::middleware('auth:sanctum', 'role:admin,stuff,member')->group(function () {
    // All Products Routes
    Route::prefix('products')->group(function () {
        Route::post('/', [ProductController::class, 'store']);
        Route::get('/all', [PublicProductController::class, 'allProduct']);
        Route::get('/{product_id}', [PublicProductController::class, 'show']);
        Route::post('/toggle-status/{product_id}', [ProductController::class, 'toggleStatus']);
        Route::put('/update/{product_id}', [ProductController::class, 'updateProduct']);
        Route::delete('/delete/{product_id}', [ProductController::class, 'deleteProduct']);
        Route::post('/add-categories/{product_id}', [CategoryController::class, 'addCategories']);
        Route::delete('/remove-categories/{product_id}', [CategoryController::class, 'removeCategories']);
        Route::post('/add-tags/{product_id}', [TagController::class, 'addTags']);
        Route::delete('/remove-tags/{product_id}', [TagController::class, 'removeTags']);
        Route::post('/add-images/{product_id}', [FileController::class, 'addImagesProduct']);
        Route::get('/files/image', [FileController::class, 'getProductFiles']);
        Route::delete('/remove-image/{product_id}', [FileController::class, 'removePeoductImage']);
        Route::prefix('bundles')->group(function () {
            Route::post('/{bundleId}', [BundleItemController::class, 'addItemsToBundle']);
            Route::post('/update-quantity/{bundleId}', [BundleItemController::class, 'updateQuantity']);
            Route::post('/toggle-bundle/{product_id}', [BundleItemController::class, 'toggleBundle']);
            Route::delete('/delete/{bundleId}', [BundleItemController::class, 'deleteBundle']);
        });
    });
    Route::get('/buying-price-items', [ProductController::class, 'getitemsByBuyingPrice']);
    Route::get('/item-buying-history/{item_id}', [ProductController::class, 'getItemBuyingHistory']);
    Route::get('/in-stock-products', [ProductController::class, 'inStockProducts']);
    Route::get('/except-bundles', [ProductController::class, 'showallproductsExceptBundles']);
    Route::get('/is-bundles', [ProductController::class, 'showallproductsIsBundles']);
});
