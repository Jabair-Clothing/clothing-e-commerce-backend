<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Challan\ChallanController;

Route::middleware('auth:sanctum', 'role:admin,stuff,member')->group(function () {
    // All Challan Routes
    Route::prefix('challans')->group(function () {
        Route::post('/', [ChallanController::class, 'store']);
        Route::get('/', [ChallanController::class, 'index']);
        Route::get('/{challans_id}', [ChallanController::class, 'show']);
        Route::put('/{challans_id}', [ChallanController::class, 'update']);
        Route::post('/upload-invoice/{challans_id}', [ChallanController::class, 'uploadInvoiceImage']);
        Route::delete('/invoices/{file_id}', [ChallanController::class, 'destroyInvoice']);
        Route::post('/add-items/{challans_id}', [ChallanController::class, 'addItemsToChallan']);
        Route::put('/challan-item/update-quantity/{challan_item_id}', [ChallanController::class, 'updateChallanItemQuantity']);
        Route::delete('/challan-item/delete/{challan_item_id}', [ChallanController::class, 'deleteChallanItem']);
    });
});
