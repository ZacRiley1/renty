<?php

namespace App\Http\Controllers;

use App\Models\RentPayment;
use App\Services\RentPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;

class RentPaymentController extends Controller
{
    public function __construct(private readonly RentPaymentService $rentPayments)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $this->rentPayments->listFor($request->user());

        return Response::api($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'paid_on' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'period_start' => ['required', 'date', 'before_or_equal:period_end'],
            'period_end' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'schedule_type' => ['required', 'in:specific_day,last_day,last_weekday'],
            'due_day' => ['nullable', 'integer', 'between:1,31'],
        ]);

        if ($validated['schedule_type'] === 'specific_day' && empty($validated['due_day'])) {
            throw ValidationException::withMessages([
                'due_day' => 'Select the day your rent falls due each month.',
            ]);
        }

        $result = $this->rentPayments->create($request->user(), $validated);

        return Response::api($result, 201);
    }

    public function verify(Request $request, RentPayment $rentPayment): JsonResponse
    {
        $result = $this->rentPayments->verify($request->user(), $rentPayment);

        return Response::api($result);
    }

    public function advance(Request $request): JsonResponse
    {
        $result = $this->rentPayments->advance($request->user());

        return Response::api($result);
    }

    public function advanceLate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'paid_on' => ['nullable', 'date'],
        ]);

        $result = $this->rentPayments->advanceLate($request->user(), $validated['paid_on'] ?? null);

        return Response::api($result);
    }
}
