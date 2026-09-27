<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateSupportTicketRequest;
use App\Http\Resources\Api\V1\AdminSupportTicketResource;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Illuminate\Support\Facades\Gate;

class SupportTicketUpdateController extends Controller
{
    public function __invoke(UpdateSupportTicketRequest $request, SupportTicket $ticket, SupportTicketService $service): AdminSupportTicketResource
    {
        Gate::authorize('manage', $ticket);

        return new AdminSupportTicketResource($service->update($request->user(), $ticket, $request->validated()));
    }
}
