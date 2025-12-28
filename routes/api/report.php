<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Report\ReportController;

Route::middleware('auth:sanctum', 'role:admin,stuff,member')->group(function () {

    // reposts routes
    Route::prefix('reports')->group(function () {
        Route::get('/expenses/monthly-total', [ReportController::class, 'getExpenseMonthly']);
        Route::get('/transitions/monthly-total', [ReportController::class, 'getMonthlyTransition']);
        Route::get('/top-selling-items', [ReportController::class, 'topSellingItems']);

        Route::get('/sales-over-time', [ReportController::class, 'salesOverTime']);
    });
});
