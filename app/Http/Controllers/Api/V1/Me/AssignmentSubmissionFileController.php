<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Me\StoreAssignmentSubmissionFilesRequest;
use App\Http\Resources\Api\V1\StudentAssignmentSubmissionResource;
use App\Models\AssignmentSubmission;
use App\Models\AssignmentSubmissionFile;
use App\Services\AssignmentFileService;
use Illuminate\Support\Facades\Gate;

class AssignmentSubmissionFileController extends Controller
{
    public function store(StoreAssignmentSubmissionFilesRequest $request, AssignmentSubmission $submission, AssignmentFileService $files): StudentAssignmentSubmissionResource
    {
        Gate::authorize('update', $submission);

        return new StudentAssignmentSubmissionResource($files->storeSubmissionFiles(
            $request->user(),
            $submission,
            $request->file('files'),
        ));
    }

    public function destroy(AssignmentSubmission $submission, AssignmentSubmissionFile $file, AssignmentFileService $files): StudentAssignmentSubmissionResource
    {
        Gate::authorize('update', $submission);

        return new StudentAssignmentSubmissionResource($files->deleteSubmissionFile(request()->user(), $file));
    }
}
