<?php

namespace App\Facades;

use App\Services\RentPaymentService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array listFor(\App\Models\User $user)
 * @method static array create(\App\Models\User $user, array $payload)
 * @method static array verify(\App\Models\User $user, \App\Models\RentPayment $rentPayment)
 * @method static array advance(\App\Models\User $user)
 *
 * @see \App\Services\RentPaymentService
 */
class RentPayments extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RentPaymentService::class;
    }
}
