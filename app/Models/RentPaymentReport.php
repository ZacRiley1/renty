<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentPaymentReport extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'rent_payment_id',
        'user_id',
        'paid_on',
        'amount',
        'reported_at',
        'verified_at',
        'status',
        'report_reference',
        'reported_to',
        'payload',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'paid_on' => 'date',
        'amount' => 'decimal:2',
        'reported_at' => 'datetime',
        'verified_at' => 'datetime',
        'payload' => 'array',
    ];

    public function rentPayment(): BelongsTo
    {
        return $this->belongsTo(RentPayment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
