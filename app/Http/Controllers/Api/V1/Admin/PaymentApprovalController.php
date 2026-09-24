<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AdminPaymentResource;
use App\Models\Payment;
use App\Services\PaymentReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentApprovalController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        Payment $payment,
        PaymentReviewService $reviewService,
    ): AdminPaymentResource {
        Gate::authorize('review', $payment);

        return new AdminPaymentResource($reviewService->approve($payment, $request->user()));
    }
}
