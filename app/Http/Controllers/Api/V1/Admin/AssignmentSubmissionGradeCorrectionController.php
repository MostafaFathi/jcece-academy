<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\CorrectAssignmentGradeRequest;
use App\Http\Resources\Api\V1\AdminAssignmentSubmissionResource;
use App\Models\AssignmentSubmission;
use App\Services\AssignmentGradingService;
use Illuminate\Support\Facades\Gate;

class AssignmentSubmissionGradeCorrectionController extends Controller
{
    public function __invoke(CorrectAssignmentGradeRequest $request, AssignmentSubmission $submission, AssignmentGradingService $grading): AdminAssignmentSubmissionResource
    {
        Gate::authorize('grade', $submission);

        return new AdminAssignmentSubmissionResource($grading->correct(
            $request->user(),
            $submission,
            (string) $request->validated('score'),
            $request->validated('feedback'),
            $request->string('reason')->toString(),
        ));
    }
}
