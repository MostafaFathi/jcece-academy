<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Me\StorePaymentRequest;
use App\Http\Resources\Api\V1\MePaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\PaymentMethod;
use App\Services\PaymentSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function store(
        StorePaymentRequest $request,
        Order $order,
        PaymentSubmissionService $submissionService,
    ): JsonResponse {
        Gate::authorize('submit', [Payment::class, $order]);
        $payment = $submissionService->submit(
            $order,
            $request->user(),
            PaymentMethod::from($request->validated('method')),
            $request->file('payment_proof'),
            $request->validated('transaction_id'),
        );

        return (new MePaymentResource($payment))->response()->setStatusCode(201);
    }
}
