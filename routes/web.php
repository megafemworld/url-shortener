<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UrlController;

Route::get('/', function () {
    return view('welcome');
});

// URL shortener redirect route
Route::get('{slug}', [UrlController::class, 'reditrect'])
    ->where('slug', '[A-Za-z0-9_-]+');
