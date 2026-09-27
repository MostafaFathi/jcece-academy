<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudentAssignmentSubmissionResource;
use App\Models\AssignmentSubmission;
use App\Services\AssignmentSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AssignmentSubmissionFinalizationController extends Controller
{
    public function __invoke(Request $request, AssignmentSubmission $submission, AssignmentSubmissionService $submissions): StudentAssignmentSubmissionResource
    {
        Gate::authorize('update', $submission);

        return new StudentAssignmentSubmissionResource($submissions->submit($request->user(), $submission));
    }
}
