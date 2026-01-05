<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Report\ReportController;

Route::middleware('auth:api', 'role:admin,stuff,member')->group(function () {

    // reposts routes
    Route::prefix('reports')->group(function () {
        Route::get('/overview', [ReportController::class, 'overview']);
        Route::get('/sales', [ReportController::class, 'sales']);
        Route::get('/receivables', [ReportController::class, 'receivables']);
        Route::get('/inventory', [ReportController::class, 'inventory']);
        Route::get('/best-sellers', [ReportController::class, 'bestSellers']);
        Route::get('/coupons', [ReportController::class, 'coupons']);
        Route::get('/wishlists', [ReportController::class, 'wishlists']);
    });
});
