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
    private const MAX_SCHEDULE_LOOKAHEAD = 240;

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
                ->with(['report', 'range'])
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
                'metadata' => $range->metadata,
            ] : null,
            'actions' => [
                'can_advance' => $pendingExists,
            ],
        ];
    }

    public function create(User $user, array $payload): array
    {
        return DB::transaction(function () use ($user, $payload): array {
            $periodStart = Carbon::parse($payload['period_start'])->startOfDay();
            $periodEnd = Carbon::parse($payload['period_end'])->endOfDay();

            if ($periodStart->gt($periodEnd)) {
                throw ValidationException::withMessages([
                    'period_start' => 'The period start must be before the end date.',
                ]);
            }

            $scheduleType = $payload['schedule_type'] ?? 'specific_day';
            $dueDay = isset($payload['due_day']) ? (int) $payload['due_day'] : null;

            if ($scheduleType === 'specific_day') {
                if (! $dueDay || $dueDay < 1 || $dueDay > 31) {
                    throw ValidationException::withMessages([
                        'due_day' => 'Select a valid payment day.',
                    ]);
                }
            }

            $schedule = $this->normalizeScheduleConfig([
                'type' => $scheduleType,
                'day' => $dueDay,
            ]);

            $firstDueDate = $this->firstDueDateForRange($periodStart, $periodEnd, $schedule);

            if (! $firstDueDate) {
                throw ValidationException::withMessages([
                    'paid_on' => 'Unable to determine a due date within the tenancy range.',
                ]);
            }

            $user->rentPaymentRanges()
                ->whereNull('completed_at')
                ->update(['completed_at' => now()]);

            $range = $user->rentPaymentRanges()->create([
                'day_of_month' => $schedule['type'] === 'specific_day' ? $schedule['day'] : 31,
                'start_date' => $periodStart->toDateString(),
                'end_date' => $periodEnd->toDateString(),
                'amount' => Arr::get($payload, 'amount'),
                'notes' => $payload['notes'] ?? null,
                'metadata' => [
                    'schedule' => $schedule,
                    'first_due_date' => $firstDueDate->toDateString(),
                ],
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
    }

    public function verify(User $user, RentPayment $rentPayment): array
    {
        if ($rentPayment->user_id !== $user->id) {
            abort(403);
        }

        if ($rentPayment->status === RentPayment::STATUS_VERIFIED) {
            return [
                'rent_payment' => $rentPayment->load(['report', 'range']),
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
                'rent_payment' => $rentPayment->load(['report', 'range']),
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

            $dueDate = Carbon::parse($pendingPayment->metadata['due_date'] ?? $pendingPayment->paid_on);
            $metadata = $pendingPayment->metadata ?? [];
            $metadata['due_date'] = $dueDate->toDateString();
            $metadata['late'] = false;
            unset($metadata['late_paid_on']);

            $pendingPayment->update([
                'status' => RentPayment::STATUS_SUBMITTED,
                'status_changed_at' => now(),
                'metadata' => $metadata,
            ]);

            $this->stats->incrementOnTimePayments($user, 1);

            $range = $pendingPayment->range;

            if ($range) {
                $nextDue = $this->nextDueDate($range, $dueDate);

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
                            'metadata' => [
                                'due_date' => $nextDue->toDateString(),
                                'late' => false,
                            ],
                        ]);

                        RentPaymentCreated::dispatch($newPayment->fresh(['report', 'range']));
                    }
                } else {
                    $range->update(['completed_at' => now()]);
                }
            }

            return $this->listFor($user);
        });
    }

    public function advanceLate(User $user, ?string $latePaidOn = null): array
    {
        return DB::transaction(function () use ($user, $latePaidOn): array {
            $pendingPayment = $user->rentPayments()
                ->where('status', RentPayment::STATUS_PENDING)
                ->orderBy('paid_on')
                ->first();

            if (! $pendingPayment) {
                return $this->listFor($user);
            }

            $dueDate = Carbon::parse($pendingPayment->metadata['due_date'] ?? $pendingPayment->paid_on);
            $actualPaidOn = $latePaidOn ? Carbon::parse($latePaidOn) : $dueDate->copy()->addDay();

            if ($actualPaidOn->lte($dueDate)) {
                throw ValidationException::withMessages([
                    'paid_on' => 'Late payment date must be after the due date.',
                ]);
            }

            $metadata = $pendingPayment->metadata ?? [];
            $metadata['due_date'] = $dueDate->toDateString();
            $metadata['late'] = true;
            $metadata['late_paid_on'] = $actualPaidOn->toDateString();

            $pendingPayment->update([
                'status' => RentPayment::STATUS_SUBMITTED,
                'status_changed_at' => now(),
                'paid_on' => $actualPaidOn->toDateString(),
                'metadata' => $metadata,
            ]);

            $this->stats->incrementOnTimePayments($user, 1);

            $range = $pendingPayment->range;

            if ($range) {
                $nextDue = $this->nextDueDate($range, $dueDate);

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
                            'metadata' => [
                                'due_date' => $nextDue->toDateString(),
                                'late' => false,
                            ],
                        ]);

                        RentPaymentCreated::dispatch($newPayment->fresh(['report', 'range']));
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
        $schedule = $this->scheduleConfig($range);
        $start = Carbon::parse($range->start_date)->startOfDay();
        $end = Carbon::parse($range->end_date)->endOfDay();

        $firstDue = $this->firstDueDateForRange($start, $end, $schedule);

        if (! $firstDue) {
            return collect();
        }

        $dates = collect();
        $cursor = $firstDue->copy();
        $iterations = 0;

        while ($cursor->lte($end) && $iterations < self::MAX_SCHEDULE_LOOKAHEAD) {
            $dates->push($cursor->copy());
            $next = $this->advanceSchedule($cursor, $schedule);

            if (! $next || $next->gt($end)) {
                break;
            }

            $cursor = $next;
            $iterations++;
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
                'metadata' => [
                    'due_date' => $dateKey,
                    'late' => false,
                ],
            ]);

            if ($status === RentPayment::STATUS_SUBMITTED) {
                $submittedCount++;
            } else {
                $pendingCreated = true;
            }

            RentPaymentCreated::dispatch($payment->fresh(['report', 'range']));
        }

        return [$submittedCount, $pendingCreated];
    }

    private function nextDueDate(RentPaymentRange $range, Carbon $reference): ?Carbon
    {
        $schedule = $this->scheduleConfig($range);
        $next = $this->advanceSchedule($reference, $schedule);
        $rangeEnd = Carbon::parse($range->end_date)->endOfDay();

        if (! $next || $next->gt($rangeEnd)) {
            return null;
        }

        return $next;
    }

    private function scheduleConfig(RentPaymentRange $range): array
    {
        $metadata = $range->metadata ?? [];
        $schedule = Arr::get($metadata, 'schedule', []);
        $fallback = $range->day_of_month ? (int) $range->day_of_month : 1;

        return $this->normalizeScheduleConfig($schedule, $fallback);
    }

    private function normalizeScheduleConfig(array $schedule, int $fallbackDay = 1): array
    {
        $type = Arr::get($schedule, 'type', 'specific_day');
        if (! in_array($type, ['specific_day', 'last_day', 'last_weekday'], true)) {
            $type = 'specific_day';
        }

        if ($type !== 'specific_day') {
            return [
                'type' => $type,
                'day' => null,
            ];
        }

        $day = Arr::get($schedule, 'day');
        if ($day !== null) {
            $day = (int) $day;
        }

        if ($day !== null && $day >= 1 && $day <= 31) {
            return [
                'type' => 'specific_day',
                'day' => $day,
            ];
        }

        $fallback = max(1, min(31, $fallbackDay));

        return [
            'type' => 'specific_day',
            'day' => $fallback,
        ];
    }

    private function computeDueDateForMonth(Carbon $month, array $schedule): ?Carbon
    {
        $monthStart = $month->copy()->startOfMonth();

        switch ($schedule['type']) {
            case 'last_day':
                return $monthStart->copy()->endOfMonth();
            case 'last_weekday':
                $last = $monthStart->copy()->endOfMonth();
                while ($last->isWeekend()) {
                    $last->subDay();
                }

                return $last;
            default:
                $day = $schedule['day'] ?? null;

                if (! $day) {
                    return null;
                }

                $day = min($day, $monthStart->daysInMonth);

                return $monthStart->copy()->day($day);
        }
    }

    private function firstDueDateForRange(Carbon $rangeStart, Carbon $rangeEnd, array $schedule): ?Carbon
    {
        $anchor = $rangeStart->copy()->startOfMonth();
        $iterations = 0;

        while ($iterations < self::MAX_SCHEDULE_LOOKAHEAD) {
            $candidate = $this->computeDueDateForMonth($anchor, $schedule);

            if (! $candidate) {
                return null;
            }

            if ($candidate->gte($rangeStart)) {
                return $candidate->gt($rangeEnd) ? null : $candidate;
            }

            $anchor = $anchor->copy()->addMonthNoOverflow()->startOfMonth();
            $iterations++;
        }

        return null;
    }

    private function advanceSchedule(Carbon $current, array $schedule): ?Carbon
    {
        $anchor = $current->copy()->addMonthNoOverflow()->startOfMonth();

        return $this->computeDueDateForMonth($anchor, $schedule);
    }
}
