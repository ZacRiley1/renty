<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'app'); // SPA shell
// catch-all for client-side routes; keep API under /api/*
Route::get('/{any}', fn () => view('app'))->where('any', '^(?!api/).*');