<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UrlController;


// Authentication Routes
Route::group(['prefix' => 'auth'], function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('login.rate.limiter');
    Route::post('register', [AuthController::class, 'register']);

    Route::middleware('auth:api')->group(function () {
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('profile', [AuthController::class, 'userProfile']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
    
});

// URL Shortening Routes
Route::group([
    'prefix' => 'urls'
], function() {
    // Public Routes
    Route::get('{slug}', [UrlController::class, 'show']);

    // Protected Routes
    Route::middleware('jwt.verify')->group(function () {
        Route::get('/', [UrlController::class, 'index']);
        Route::post('/', [UrlController::class, 'store']);
        Route::put('{slug}', [UrlController::class, 'update']);
        Route::delete('{slug}', [UrlController::class, 'destroy']);
        Route::get('{slug}/analytics', [UrlController::class, 'getAnalytics']);
    });
});

// Redirect route (web-likw route within API for the actual URL redirection)
Route::get('go/{slug}', [UrlController::class, 'redirect'])->name('url.redirect');