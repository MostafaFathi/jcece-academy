<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudentSupportTicketResource;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Illuminate\Http\Request;

class SupportTicketReopeningController extends Controller
{
    public function __invoke(Request $request, int $ticket, SupportTicketService $service): StudentSupportTicketResource
    {
        $owned = SupportTicket::query()->whereBelongsTo($request->user())->findOrFail($ticket);

        return new StudentSupportTicketResource($service->reopen($request->user(), $owned));
    }
}
