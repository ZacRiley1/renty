<?php

use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;

Route::view('/', 'app');
Route::get('/sanctum/csrf-cookie', [CsrfCookieController::class, 'show']);
Route::get('/{any}', fn () => view('app'))->where('any', '^(?!api/).*');
