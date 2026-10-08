<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\RejectPaymentRequest;
use App\Http\Resources\Api\V1\AdminPaymentResource;
use App\Models\Payment;
use App\Services\PaymentReviewService;
use Illuminate\Support\Facades\Gate;

class PaymentRejectionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        RejectPaymentRequest $request,
        Payment $payment,
        PaymentReviewService $reviewService,
    ): AdminPaymentResource {
        Gate::authorize('review', $payment);

        return new AdminPaymentResource($reviewService->reject($payment, $request->validated('rejection_reason'), $request->user()));
    }
}
