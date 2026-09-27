<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Me\StoreSupportTicketRequest;
use App\Http\Resources\Api\V1\StudentSupportTicketResource;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SupportTicketController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return StudentSupportTicketResource::collection(SupportTicket::query()->whereBelongsTo($request->user())->with(['relatedOrder:id,order_number', 'relatedCourse:id,title,slug'])->latest()->paginate(25));
    }

    public function store(StoreSupportTicketRequest $request, SupportTicketService $service): JsonResponse
    {
        Gate::authorize('create', SupportTicket::class);
        $ticket = $service->create($request->user(), $request->safe()->except('attachments'), $request->file('attachments', []));

        return (new StudentSupportTicketResource($ticket))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $ticket, SupportTicketService $service): StudentSupportTicketResource
    {
        $owned = SupportTicket::query()->whereBelongsTo($request->user())->findOrFail($ticket);

        return new StudentSupportTicketResource($service->load($owned));
    }
}
