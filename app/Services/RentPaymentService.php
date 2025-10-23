<?php

namespace App\Services;

use App\Events\RentPaymentCreated;
use App\Events\RentPaymentVerified;
use App\Models\RentPayment;
use App\Models\User;
use App\Models\RentPaymentRange;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RentPaymentService
{
    public function __construct(private readonly UserPaymentStatsService $stats)
    {
    }

    public function listFor(User $user): array
    {
        $range = $this->currentRange($user);
        $pendingExists = $user->rentPayments()
            ->where('status', RentPayment::STATUS_PENDING)
            ->exists();

        return [
            'rent_payments' => $user->rentPayments()
                ->with('report')
                ->orderByDesc('paid_on')
                ->orderByDesc('created_at')
                ->get(),
            'stats' => $this->stats->snapshot($user),
            'range' => $range ? [
                'id' => $range->id,
                'start_date' => $range->start_date->toDateString(),
                'end_date' => $range->end_date->toDateString(),
                'day_of_month' => $range->day_of_month,
                'completed' => (bool) $range->completed_at,
            ] : null,
            'actions' => [
                'can_advance' => $pendingExists,
            ],
        ];
    }

    public function create(User $user, array $payload): array
    {
        $result = DB::transaction(function () use ($user, $payload): array {
            $periodStart = Carbon::parse($payload['period_start'])->startOfDay();
            $periodEnd = Carbon::parse($payload['period_end'])->endOfDay();

            if ($periodStart->gt($periodEnd)) {
                throw ValidationException::withMessages([
                    'period_start' => 'The period start must be before the end date.',
                ]);
            }

            $user->rentPaymentRanges()
                ->whereNull('completed_at')
                ->update(['completed_at' => now()]);

            $dueDate = Carbon::parse($payload['paid_on']);
            $range = $user->rentPaymentRanges()->create([
                'day_of_month' => $dueDate->day,
                'start_date' => $periodStart->toDateString(),
                'end_date' => $periodEnd->toDateString(),
                'amount' => Arr::get($payload, 'amount'),
                'notes' => $payload['notes'] ?? null,
            ]);

            [$submittedCount, $pendingCreated] = $this->seedRangePayments($user, $range);

            if ($submittedCount > 0) {
                $this->stats->incrementOnTimePayments($user, $submittedCount);
            } else {
                $this->stats->snapshot($user);
            }

            if (! $pendingCreated) {
                $range->update(['completed_at' => now()]);
            }

            return $this->listFor($user);
        });

        return $result;
    }

    public function verify(User $user, RentPayment $rentPayment): array
    {
        if ($rentPayment->user_id !== $user->id) {
            abort(403);
        }

        if ($rentPayment->status === RentPayment::STATUS_VERIFIED) {
            return [
                'rent_payment' => $rentPayment->load('report'),
                'stats' => $this->stats->snapshot($user),
            ];
        }

        if ($rentPayment->status !== RentPayment::STATUS_SUBMITTED) {
            throw ValidationException::withMessages([
                'status' => 'Payment must be submitted before verification.',
            ]);
        }

        $result = DB::transaction(function () use ($rentPayment): array {
            $now = now();

            $rentPayment->update([
                'status' => RentPayment::STATUS_VERIFIED,
                'status_changed_at' => $now,
                'verified_at' => $now,
            ]);

            $rentPayment->report()->create([
                'user_id' => $rentPayment->user_id,
                'paid_on' => $rentPayment->paid_on,
                'amount' => $rentPayment->amount,
                'reported_at' => $now,
                'verified_at' => $now,
                'status' => 'verified',
            ]);

            $rentPayment->refresh();

            $stats = $this->stats->recordVerifiedPayment($rentPayment);

            RentPaymentVerified::dispatch($rentPayment);

            return [
                'rent_payment' => $rentPayment->load('report'),
                'stats' => $stats,
            ];
        });

        return $result;
    }

    public function advance(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            $pendingPayment = $user->rentPayments()
                ->where('status', RentPayment::STATUS_PENDING)
                ->orderBy('paid_on')
                ->first();

            if (! $pendingPayment) {
                return $this->listFor($user);
            }

            $pendingPayment->update([
                'status' => RentPayment::STATUS_SUBMITTED,
                'status_changed_at' => now(),
            ]);

            $this->stats->incrementOnTimePayments($user, 1);

            $range = $pendingPayment->range;

            if ($range) {
                $nextDue = $this->nextDueDate($range, Carbon::parse($pendingPayment->paid_on));

                if ($nextDue && $nextDue->lte(Carbon::parse($range->end_date))) {
                    $alreadyExists = $range->payments()
                        ->whereDate('paid_on', $nextDue->toDateString())
                        ->exists();

                    if (! $alreadyExists) {
                        $newPayment = $range->payments()->create([
                            'user_id' => $user->id,
                            'period_start' => $nextDue->copy()->startOfMonth()->max($range->start_date)->toDateString(),
                            'period_end' => $nextDue->copy()->endOfMonth()->min($range->end_date)->toDateString(),
                            'paid_on' => $nextDue->toDateString(),
                            'amount' => $range->amount,
                            'notes' => $range->notes,
                            'status' => RentPayment::STATUS_PENDING,
                            'status_changed_at' => now(),
                        ]);

                        RentPaymentCreated::dispatch($newPayment->fresh('report'));
                    }
                } else {
                    $range->update(['completed_at' => now()]);
                }
            }

            return $this->listFor($user);
        });
    }

    private function currentRange(User $user): ?RentPaymentRange
    {
        return $user->rentPaymentRanges()
            ->whereNull('completed_at')
            ->latest('end_date')
            ->first();
    }

    private function generateDueDates(RentPaymentRange $range): Collection
    {
        $dates = collect();
        $start = $this->alignToNextPaymentDate(Carbon::parse($range->start_date)->copy(), (int) $range->day_of_month);
        $end = Carbon::parse($range->end_date)->endOfDay();

        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $dates->push($cursor->copy());
            $cursor->addMonthNoOverflow();
            $cursor->day = min($range->day_of_month, $cursor->daysInMonth);
        }

        return $dates;
    }

    private function seedRangePayments(User $user, RentPaymentRange $range): array
    {
        $dueDates = $this->generateDueDates($range);
        $existingDates = $user->rentPayments()
            ->whereBetween('paid_on', [$range->start_date, $range->end_date])
            ->pluck('paid_on')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        $submittedCount = 0;
        $pendingCreated = false;
        $today = now()->startOfDay();

        foreach ($dueDates as $dueDate) {
            $dateKey = $dueDate->toDateString();

            if (in_array($dateKey, $existingDates, true)) {
                continue;
            }

            $status = $dueDate->lte($today) ? RentPayment::STATUS_SUBMITTED : RentPayment::STATUS_PENDING;

            if ($status === RentPayment::STATUS_PENDING && $pendingCreated) {
                break;
            }

            $payment = $range->payments()->create([
                'user_id' => $user->id,
                'period_start' => $dueDate->copy()->startOfMonth()->max($range->start_date)->toDateString(),
                'period_end' => $dueDate->copy()->endOfMonth()->min($range->end_date)->toDateString(),
                'paid_on' => $dateKey,
                'amount' => $range->amount,
                'notes' => $range->notes,
                'status' => $status,
                'status_changed_at' => now(),
            ]);

            if ($status === RentPayment::STATUS_SUBMITTED) {
                $submittedCount++;
            } else {
                $pendingCreated = true;
            }

            RentPaymentCreated::dispatch($payment->fresh('report'));
        }

        return [$submittedCount, $pendingCreated];
    }

    private function alignToNextPaymentDate(Carbon $start, int $day): Carbon
    {
        $candidate = Carbon::create(
            $start->year,
            $start->month,
            min($day, $start->daysInMonth),
            0,
            0,
            0,
            $start->timezone
        );

        if ($candidate->lt($start)) {
            $candidate->addMonthNoOverflow();
            $candidate->day = min($day, $candidate->daysInMonth);
        }

        return $candidate;
    }

    private function nextDueDate(RentPaymentRange $range, Carbon $reference): ?Carbon
    {
        $next = $reference->copy()->addMonthNoOverflow();
        $next->day = min($range->day_of_month, $next->daysInMonth);

        if ($next->lt(Carbon::parse($range->start_date))) {
            return $this->alignToNextPaymentDate(Carbon::parse($range->start_date)->copy(), $range->day_of_month);
        }

        return $next;
    }
}
