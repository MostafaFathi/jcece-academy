<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Me\SaveAssignmentSubmissionRequest;
use App\Http\Resources\Api\V1\StudentAssignmentSubmissionResource;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Services\AssignmentAccessService;
use App\Services\AssignmentSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AssignmentSubmissionController extends Controller
{
    public function index(Request $request, Assignment $assignment, AssignmentAccessService $access): AnonymousResourceCollection
    {
        $access->requireAvailable($request->user(), $assignment->load('course'));
        $submissions = AssignmentSubmission::query()
            ->whereBelongsTo($assignment)
            ->whereBelongsTo($request->user())
            ->with('files')
            ->latest()
            ->get();

        return StudentAssignmentSubmissionResource::collection($submissions);
    }

    public function store(Request $request, Assignment $assignment, AssignmentSubmissionService $submissions): JsonResponse
    {
        Gate::authorize('create', AssignmentSubmission::class);

        return (new StudentAssignmentSubmissionResource($submissions->startDraft($request->user(), $assignment->load('course'))))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, AssignmentSubmission $submission, AssignmentSubmissionService $submissions): StudentAssignmentSubmissionResource
    {
        Gate::authorize('view', $submission);
        $submissions->authorizeOwner($request->user(), $submission);

        return new StudentAssignmentSubmissionResource($submissions->loadSubmission($submission));
    }

    public function update(SaveAssignmentSubmissionRequest $request, AssignmentSubmission $submission, AssignmentSubmissionService $submissions): StudentAssignmentSubmissionResource
    {
        Gate::authorize('update', $submission);

        return new StudentAssignmentSubmissionResource($submissions->saveDraft($request->user(), $submission, $request->validated()));
    }
}
