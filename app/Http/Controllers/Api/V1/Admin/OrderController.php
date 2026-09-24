<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListOrdersRequest;
use App\Http\Resources\Api\V1\AdminOrderResource;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(ListOrdersRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Order::class);
        $filters = $request->validated();
        $orders = Order::query()
            ->with('user')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                $query->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%");
            }))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['user_id'] ?? null, fn (Builder $query, int $userId) => $query->where('user_id', $userId))
            ->latest()
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return AdminOrderResource::collection($orders);
    }

    public function show(Order $order): AdminOrderResource
    {
        Gate::authorize('view', $order);

        return new AdminOrderResource($order->load([
            'user',
            'items.packageCourses',
            'payments.approver',
        ]));
    }
}
