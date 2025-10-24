<?php

namespace App\Listeners;

use App\Events\RentPaymentVerified;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RecordRentPaymentVerification
{
    public function __invoke(RentPaymentVerified $event): void
    {
        Log::info('rent-payment.verified', [
            'payment_id' => $event->rentPayment->id,
            'user_id' => $event->rentPayment->user_id,
            'amount' => Str::currency($event->rentPayment->amount),
            'verified_at' => $event->rentPayment->verified_at,
            'status' => $event->rentPayment->status,
        ]);
    }
}
