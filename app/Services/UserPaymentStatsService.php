<?php

namespace App\Services;

use App\Models\RentPayment;
use App\Models\User;
use App\Models\UserPaymentStat;
use Closure;
use Illuminate\Support\Facades\DB;

class UserPaymentStatsService
{
    public function snapshot(User $user): array
    {
        $stats = UserPaymentStat::firstOrCreate([
            'user_id' => $user->id,
        ]);

        return $this->format($stats);
    }

    public function incrementOnTimePayments(User $user, int $count = 1): array
    {
        if ($count < 1) {
            return $this->snapshot($user);
        }

        $stats = $this->withStatsLock($user, function (UserPaymentStat $stats) use ($count): UserPaymentStat {
            $stats->on_time_payments += $count;
            $stats->payment_streak = max($stats->payment_streak, $stats->on_time_payments);
            $stats->save();

            return $stats;
        });

        return $this->format($stats);
    }

    public function recordVerifiedPayment(RentPayment $rentPayment): array
    {
        $stats = $this->withStatsLock($rentPayment->user, function (UserPaymentStat $stats) use ($rentPayment): UserPaymentStat {
            $stats->reports_sent += 1;
            $stats->current_score = (int) min(
                $stats->goal_score,
                $stats->starting_score + ($stats->reports_sent * 2)
            );
            $stats->last_verified_payment_at = $rentPayment->verified_at;
            $stats->save();

            return $stats;
        });

        return $this->format($stats);
    }

    private function withStatsLock(User $user, Closure $callback): UserPaymentStat
    {
        return DB::transaction(function () use ($user, $callback): UserPaymentStat {
            $stats = UserPaymentStat::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $stats) {
                $stats = UserPaymentStat::create([
                    'user_id' => $user->id,
                ]);
            }

            return $callback($stats);
        });
    }

    private function format(UserPaymentStat $stats): array
    {
        return [
            'score' => [
                'start' => $stats->starting_score,
                'current' => $stats->current_score,
                'goal' => $stats->goal_score,
            ],
            'totals' => [
                'on_time_payments' => $stats->on_time_payments,
                'payment_streak' => $stats->payment_streak,
                'reports_sent' => $stats->reports_sent,
            ],
            'meta' => [
                'last_verified_payment_at' => optional($stats->last_verified_payment_at)?->toIso8601String(),
            ],
        ];
    }
}
