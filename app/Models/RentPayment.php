<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RentPayment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'rent_payment_range_id',
        'period_start',
        'period_end',
        'paid_on',
        'amount',
        'status',
        'status_changed_at',
        'verified_at',
        'external_reference',
        'notes',
        'metadata',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'paid_on' => 'date',
        'amount' => 'decimal:2',
        'status_changed_at' => 'datetime',
        'verified_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function range(): BelongsTo
    {
        return $this->belongsTo(RentPaymentRange::class, 'rent_payment_range_id');
    }

    public function report(): HasOne
    {
        return $this->hasOne(RentPaymentReport::class);
    }
}
