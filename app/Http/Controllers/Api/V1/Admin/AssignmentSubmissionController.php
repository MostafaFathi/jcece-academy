<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AdminAssignmentSubmissionResource;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AssignmentSubmissionController extends Controller
{
    public function index(Assignment $assignment): AnonymousResourceCollection
    {
        Gate::authorize('reviewSubmissions', $assignment);
        $submissions = $assignment->submissions()
            ->with(['user', 'files', 'grader', 'gradingEvents.reviewer'])
            ->get();

        return AdminAssignmentSubmissionResource::collection($submissions);
    }

    public function show(Assignment $assignment, AssignmentSubmission $submission): AdminAssignmentSubmissionResource
    {
        Gate::authorize('review', $submission);
        abort_unless($submission->assignment_id === $assignment->id, 404);

        return new AdminAssignmentSubmissionResource($submission->load(['assignment.course', 'user', 'files', 'grader', 'gradingEvents.reviewer']));
    }
}
