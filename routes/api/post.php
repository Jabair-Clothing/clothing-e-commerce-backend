<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Post\PostController;


Route::middleware('auth:sanctum', 'role:admin,stuff,member')->group(function () {
    Route::prefix('posts')->group(function () {
        Route::post('/', [PostController::class, 'store']);
        Route::get('/', [PostController::class, 'index']);
        Route::put('/{post_id}', [PostController::class, 'update']);
        Route::patch('/status/{post_id}', [PostController::class, 'toggleStatus']);
        Route::delete('/{post_id}', [PostController::class, 'destroy']);
    });
});

Route::get('/posts/published', [PostController::class, 'indexPublished']);
Route::get('/posts/{slug}', [PostController::class, 'show']);
