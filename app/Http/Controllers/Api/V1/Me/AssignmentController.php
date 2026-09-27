<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\AssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudentAssignmentResource;
use App\Models\Assignment;
use App\Services\AssignmentAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AssignmentController extends Controller
{
    public function index(Request $request, AssignmentAccessService $access): AnonymousResourceCollection
    {
        $assignments = Assignment::query()
            ->where('status', AssignmentStatus::Published)
            ->where(fn ($query) => $query->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->with(['course', 'attachments'])
            ->orderBy('course_id')
            ->orderBy('id')
            ->get()
            ->filter(fn (Assignment $assignment): bool => $access->isAvailableTo($request->user(), $assignment))
            ->values();

        return StudentAssignmentResource::collection($assignments);
    }

    public function show(Request $request, Assignment $assignment, AssignmentAccessService $access): StudentAssignmentResource
    {
        $access->requireAvailable($request->user(), $assignment->load('course'));

        return new StudentAssignmentResource($assignment->load('attachments'));
    }
}
