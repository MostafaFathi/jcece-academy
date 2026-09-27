<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListSupportTicketsRequest;
use App\Http\Resources\Api\V1\AdminSupportTicketResource;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SupportTicketController extends Controller
{
    public function index(ListSupportTicketsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', SupportTicket::class);
        $filters = $request->validated();
        $query = SupportTicket::query()->with(['user:id,name', 'assignee:id,name', 'relatedOrder:id,order_number', 'relatedCourse:id,title,slug'])
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($filters['category'] ?? null, fn ($q, $value) => $q->where('category', $value))
            ->when($filters['priority'] ?? null, fn ($q, $value) => $q->where('priority', $value))
            ->when(array_key_exists('assigned_to', $filters), fn ($q) => $filters['assigned_to'] === null ? $q->whereNull('assigned_to') : $q->where('assigned_to', $filters['assigned_to']))
            ->when($filters['created_from'] ?? null, fn ($q, $value) => $q->whereDate('created_at', '>=', $value))
            ->when($filters['created_to'] ?? null, fn ($q, $value) => $q->whereDate('created_at', '<=', $value));

        return AdminSupportTicketResource::collection($query->latest()->paginate($filters['per_page'] ?? 25)->withQueryString());
    }

    public function show(SupportTicket $ticket, SupportTicketService $service): AdminSupportTicketResource
    {
        Gate::authorize('viewAny', SupportTicket::class);

        return new AdminSupportTicketResource($service->load($ticket, true));
    }
}
