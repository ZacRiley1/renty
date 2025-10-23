<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RentPaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::middleware('web')->group(function (): void {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('dashboard', DashboardController::class);

    Route::get('rent-payments', [RentPaymentController::class, 'index']);
    Route::post('rent-payments', [RentPaymentController::class, 'store']);
    Route::post('rent-payments/advance', [RentPaymentController::class, 'advance']);
    Route::post('rent-payments/{rentPayment}/verify', [RentPaymentController::class, 'verify'])
        ->whereNumber('rentPayment');
});
