<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Carrer\CarrerController;
use App\Http\Controllers\Carrer\CareerFormController;


Route::prefix('careers')->group(function () {
    Route::get('/active', [CarrerController::class, 'activeCareers']);
    Route::post('/forms/{career_id}', [CareerFormController::class, 'store']);
    Route::get('/forms/{career_id}', [CareerFormController::class, 'index']);
});

Route::middleware('auth:sanctum', 'role:admin,stuff,member')->group(function () {
    Route::prefix('careers')->group(function () {
        Route::get('/', [CarrerController::class, 'index']);
        Route::get('/{careers_id}', [CarrerController::class, 'show']);
        Route::post('/', [CarrerController::class, 'store']);
        Route::put('/{careers_id}', [CarrerController::class, 'update']);
        Route::delete('/{careers_id}', [CarrerController::class, 'destroy']);
        Route::patch('/status/{careers_id}', [CarrerController::class, 'changeStatus']);
        Route::get('/forms/{career_id}/{form_id}', [CareerFormController::class, 'show']);
    });
});
