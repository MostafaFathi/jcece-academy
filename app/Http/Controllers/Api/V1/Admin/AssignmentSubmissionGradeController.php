<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\GradeAssignmentSubmissionRequest;
use App\Http\Resources\Api\V1\AdminAssignmentSubmissionResource;
use App\Models\AssignmentSubmission;
use App\Services\AssignmentGradingService;
use Illuminate\Support\Facades\Gate;

class AssignmentSubmissionGradeController extends Controller
{
    public function __invoke(GradeAssignmentSubmissionRequest $request, AssignmentSubmission $submission, AssignmentGradingService $grading): AdminAssignmentSubmissionResource
    {
        Gate::authorize('grade', $submission);
        $graded = $grading->grade(
            $request->user(),
            $submission,
            (string) $request->validated('score'),
            $request->validated('feedback'),
        );

        return new AdminAssignmentSubmissionResource($graded);
    }
}
