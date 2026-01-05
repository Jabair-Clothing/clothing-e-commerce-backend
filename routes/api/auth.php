<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Email\EmailController;
use App\Http\Controllers\Passwort\ForgotPasswordController;
use App\Http\Controllers\Auth\AuthController;

// Admin Auth
Route::middleware('auth:api', 'role:admin')->group(function () {
    Route::delete('/stuff/delete/{userid}', [AuthController::class, 'destroy']);
    Route::patch('/users/toggle-status/{userid}', [AuthController::class, 'toggleStatus']);
});
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// User Auth
Route::prefix('users')->group(function () {
    Route::post('/register', [AuthController::class, 'Userregister']);
    Route::post('/login', [AuthController::class, 'Userlogin']);
    Route::delete('/{user}', [AuthController::class, 'destroyuser']);
});
// Email varifications
Route::get('/email/verify/{id}/{hash}', [EmailController::class, 'verify'])
    ->name('verification.verify');

Route::post('/email/resend-verification', [EmailController::class, 'resendVerificationEmail'])
    ->name('verification.resend');

// Forgot Password
Route::post('/password/forgot', [ForgotPasswordController::class, 'forgotPassword'])
    ->name('password.forgot');

// Reset Password
Route::post('/password/reset', [ForgotPasswordController::class, 'resetPassword'])
    ->name('password.reset');


Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/update-user-info', [AuthController::class, 'updateUserInfo']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
});
