<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Expense\ExpenseController;

Route::middleware('auth:sanctum', 'role:admin,stuff,member')->group(function () {
    // Expense Routes
    Route::prefix('expenses')->group(function () {
        Route::post('/', [ExpenseController::class, 'store']);
        Route::get('/', [ExpenseController::class, 'index']);
        Route::put('/{expense_id}', [ExpenseController::class, 'update']);
        Route::get('/{expense_id}', [ExpenseController::class, 'show']);
        Route::delete('/prove/{file_id}', [ExpenseController::class, 'destroyProve']);
        Route::delete('/{expense_id}', [ExpenseController::class, 'destroy']);
    });
});
