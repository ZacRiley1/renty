<?php

namespace App\Listeners;

use App\Events\RentPaymentCreated;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RecordRentPaymentCreation
{
    public function __invoke(RentPaymentCreated $event): void
    {
        Log::info('rent-payment.created', [
            'payment_id' => $event->rentPayment->id,
            'user_id' => $event->rentPayment->user_id,
            'amount' => Str::currency($event->rentPayment->amount),
            'paid_on' => $event->rentPayment->paid_on,
            'status' => $event->rentPayment->status,
        ]);
    }
}
