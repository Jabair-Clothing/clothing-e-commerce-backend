<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Supplier\SupplierController;

Route::middleware('auth:sanctum', 'role:admin,stuff,member')->group(function () {

    // All Suppliers Routes
    Route::prefix('suppliers')->group(function () {
        Route::post('/', [SupplierController::class, 'store']);
        Route::get('/', [SupplierController::class, 'index']);
        Route::get('/{suppliers_id}', [SupplierController::class, 'show']);
        Route::put('/{suppliers_id}', [SupplierController::class, 'update']);
        Route::delete('/{suppliers_id}', [SupplierController::class, 'delete']);

        Route::put('/update-paid-amount/{suppliers_id}', [SupplierController::class, 'updatePaidAmount']);
    });
});
