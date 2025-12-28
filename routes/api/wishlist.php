<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Wishlist\WishlistController;

Route::middleware('auth:sanctum')->group(function () {

    // whshltist routes
    Route::prefix('wishlist')->group(function () {
        Route::post('/', [WishlistController::class, 'store']);
        Route::get('/', [WishlistController::class, 'show']);
        Route::delete('/{wishlist_id}', [WishlistController::class, 'destroy']);
    });
});
