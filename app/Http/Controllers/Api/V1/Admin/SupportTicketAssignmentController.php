<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AssignSupportTicketRequest;
use App\Http\Resources\Api\V1\AdminSupportTicketResource;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportTicketService;
use Illuminate\Support\Facades\Gate;

class SupportTicketAssignmentController extends Controller
{
    public function __invoke(AssignSupportTicketRequest $request, SupportTicket $ticket, SupportTicketService $service): AdminSupportTicketResource
    {
        Gate::authorize('manage', $ticket);
        $assignee = User::query()->findOrFail($request->integer('assigned_to'));

        return new AdminSupportTicketResource($service->assign($request->user(), $ticket, $assignee, $request->validated('expected_updated_at')));
    }
}
