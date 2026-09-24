<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListPaymentsRequest;
use App\Http\Resources\Api\V1\AdminPaymentResource;
use App\Models\Payment;
use App\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function index(ListPaymentsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Payment::class);
        $filters = $request->validated();
        $payments = Payment::query()
            ->with(['order.user', 'approver'])
            ->where('status', $filters['status'] ?? PaymentStatus::PendingReview->value)
            ->when($filters['order_id'] ?? null, fn (Builder $query, int $orderId) => $query->where('order_id', $orderId))
            ->oldest()
            ->orderBy('id')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return AdminPaymentResource::collection($payments);
    }

    public function show(Payment $payment): AdminPaymentResource
    {
        Gate::authorize('view', $payment);

        return new AdminPaymentResource($payment->load(['order.user', 'approver']));
    }
}
