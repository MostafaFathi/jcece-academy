<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Me\StoreSupportTicketReplyRequest;
use App\Http\Resources\Api\V1\StudentSupportTicketMessageResource;
use App\Models\SupportTicket;
use App\Services\SupportTicketMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupportTicketMessageController extends Controller
{
    public function index(Request $request, int $ticket): AnonymousResourceCollection
    {
        $owned = SupportTicket::query()->whereBelongsTo($request->user())->findOrFail($ticket);

        return StudentSupportTicketMessageResource::collection($owned->visibleMessages()->with(['user:id,name', 'attachments'])->paginate(25));
    }

    public function store(StoreSupportTicketReplyRequest $request, int $ticket, SupportTicketMessageService $service): JsonResponse
    {
        $owned = SupportTicket::query()->whereBelongsTo($request->user())->findOrFail($ticket);
        $message = $service->post($request->user(), $owned, $request->string('body')->toString(), false, $request->file('attachments', []));

        return (new StudentSupportTicketMessageResource($message))->response()->setStatusCode(201);
    }
}
