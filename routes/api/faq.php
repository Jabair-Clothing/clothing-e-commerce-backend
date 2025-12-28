<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FAQ\FaqController;


Route::get('/faqs/active', [FAQController::class, 'indexactive']);

Route::middleware('auth:sanctum', 'role:admin,stuff,member')->group(function () {
    Route::prefix('faqs')->group(function () {
        Route::get('/', [FAQController::class, 'index']);
        Route::post('/', [FAQController::class, 'store']);
        Route::put('/{id}', [FAQController::class, 'update']);
        Route::delete('/{id}', [FAQController::class, 'destroy']);
        Route::patch('/status/{id}', [FAQController::class, 'toggleStatus']);
    });
});
