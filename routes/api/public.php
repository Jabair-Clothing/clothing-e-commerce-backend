<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Contact\ContactController;
use App\Http\Controllers\Ambassador\{AmbassadorController, OurambassadorController};
use App\Http\Controllers\Company\CompanyController;
use App\Http\Controllers\Project\ProjectController;

// those are zantech landing page apis
Route::middleware('auth:sanctum', 'role:admin,stuff,member')->group(function () {

    // Contact Us
    Route::prefix('contact')->group(function () {
        Route::get('/', [ContactController::class, 'index']);
        Route::delete('/{contact_id}', [ContactController::class, 'destroy']);
    });

    Route::prefix('ambassadors')->group(function () {
        Route::get('/', [AmbassadorController::class, 'index']);
        Route::delete('/{id}', [AmbassadorController::class, 'destroy']);
    });

    // ourambassadors
    Route::prefix('ourambassadors')->group(function () {
        Route::get('/', [OurambassadorController::class, 'index']);
        Route::post('/', [OurambassadorController::class, 'store']);
        Route::post('/{id}', [OurambassadorController::class, 'update']);
        Route::delete('/{id}', [OurambassadorController::class, 'destroy']);
    });

    // Company
    Route::prefix('company')->group(function () {
        Route::put('/{id}', [CompanyController::class, 'update']);
    });

    // project
    Route::prefix('projects')->group(function () {
        Route::get('/', [ProjectController::class, 'index']);
        Route::post('/', [ProjectController::class, 'store']);
        Route::post('/{id}', [ProjectController::class, 'update']);
        Route::delete('/{id}', [ProjectController::class, 'destroy']);

        Route::post('/technologie/{project_id}', [ProjectController::class, 'addTechnologies']);
        Route::delete('/technologie/{technologi_id}', [ProjectController::class, 'deleteTechnologies']);
    });
});



// Public Routes hi
// Contact Us
Route::prefix('contact')->group(function () {
    Route::post('/', [ContactController::class, 'store']);
});


// Ambassador Application
Route::prefix('ambassadors')->group(function () {
    Route::post('/', [AmbassadorController::class, 'store']);
});

// ourambassadors
Route::prefix('ourambassadors')->group(function () {
    Route::get('/active', [OurambassadorController::class, 'active']);
});


// Company
Route::prefix('company')->group(function () {
    Route::get('/', [CompanyController::class, 'show']);
});

// project
Route::prefix('projects')->group(function () {
    Route::get('/active', [ProjectController::class, 'getallactiveproject']);
    Route::get('/{slug}', [ProjectController::class, 'show']);
});
