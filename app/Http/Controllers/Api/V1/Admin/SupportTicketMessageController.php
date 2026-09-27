<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreSupportTicketMessageRequest;
use App\Http\Resources\Api\V1\AdminSupportTicketMessageResource;
use App\Models\SupportTicket;
use App\Services\SupportTicketMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SupportTicketMessageController extends Controller
{
    public function index(SupportTicket $ticket): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', SupportTicket::class);

        return AdminSupportTicketMessageResource::collection($ticket->messages()->with(['user:id,name', 'attachments'])->paginate(25));
    }

    public function store(StoreSupportTicketMessageRequest $request, SupportTicket $ticket, SupportTicketMessageService $service): JsonResponse
    {
        Gate::authorize('replyAsStaff', $ticket);
        $message = $service->post($request->user(), $ticket, $request->string('body')->toString(), $request->boolean('is_internal'), $request->file('attachments', []));

        return (new AdminSupportTicketMessageResource($message))->response()->setStatusCode(201);
    }
}
