<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPaymentStat extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'starting_score',
        'current_score',
        'goal_score',
        'on_time_payments',
        'payment_streak',
        'reports_sent',
        'last_verified_payment_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'last_verified_payment_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
