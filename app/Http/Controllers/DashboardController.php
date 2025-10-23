<?php

namespace App\Http\Controllers;

use App\Facades\RentPayments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = RentPayments::listFor($request->user());

        return Response::api($data);
    }
}
