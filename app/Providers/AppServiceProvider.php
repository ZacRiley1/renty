<?php

namespace App\Providers;

use App\Events\RentPaymentCreated;
use App\Events\RentPaymentVerified;
use App\Listeners\RecordRentPaymentCreation;
use App\Listeners\RecordRentPaymentVerification;
use App\Services\RentPaymentService;
use App\Services\UserPaymentStatsService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(UserPaymentStatsService::class);
        $this->app->singleton(RentPaymentService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(RentPaymentCreated::class, RecordRentPaymentCreation::class);
        Event::listen(RentPaymentVerified::class, RecordRentPaymentVerification::class);

        Response::macro('api', function (array $payload, int $status = 200, array $headers = []) {
            return response()->json([
                'data' => $payload,
                'meta' => [
                    'served_at' => now()->toIso8601String(),
                ],
            ], $status, $headers);
        });

        Str::macro('currency', function (float|string $value, string $currency = 'GBP'): string {
            $number = is_numeric($value) ? (float) $value : 0.0;

            return sprintf('%s %s', number_format($number, 2, '.', ','), strtoupper($currency));
        });
    }
}
