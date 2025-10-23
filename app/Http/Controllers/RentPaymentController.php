<?php

namespace App\Http\Controllers;

use App\Models\RentPayment;
use App\Services\RentPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

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
            'paid_on' => ['required', 'date', 'after_or_equal:period_start', 'before_or_equal:period_end'],
            'amount' => ['required', 'numeric', 'min:0'],
            'period_start' => ['required', 'date', 'before_or_equal:period_end'],
            'period_end' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

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
}
