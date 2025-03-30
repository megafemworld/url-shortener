<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UrlController;


// Authentication Routes
Route::group(['prefix' => 'auth'], function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::get('profile', [AuthController::class, 'userProfile']); 
});

// URL Shortening Routes
Route::group([
    'prefix' => 'urls'
], function() {
    // Public Routes
    Route::get('{slug}', UrlController::class, 'show');

    // Protected Routes
    Route::middleware('auth:api')->group(function () {
        Route::get('/', [UrlController::class, 'index']);
        Route::post('/', [UrlController::class, 'store']);
        Route::put('{slug}', [UrlController::class, 'update']);
        Route::delete('{slug}', [UrlController::class, 'destroy']);
        Route::get('{slug}/analytics', [UrlController::class, 'getAnalytics']);
    });
});

// Redirect route (web-likw route within API for the actual URL redirection)
Route::get('go/{slug}', [UrlController::class, 'redirect'])->name('url.redirect');