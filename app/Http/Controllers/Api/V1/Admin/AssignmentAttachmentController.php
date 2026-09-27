<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreAssignmentAttachmentRequest;
use App\Http\Resources\Api\V1\AssignmentAttachmentResource;
use App\Models\Assignment;
use App\Models\AssignmentAttachment;
use App\Services\AssignmentFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AssignmentAttachmentController extends Controller
{
    public function index(Assignment $assignment): AnonymousResourceCollection
    {
        Gate::authorize('view', $assignment);

        return AssignmentAttachmentResource::collection($assignment->attachments()->get());
    }

    public function store(StoreAssignmentAttachmentRequest $request, Assignment $assignment, AssignmentFileService $files): JsonResponse
    {
        Gate::authorize('update', $assignment);
        $attachment = $files->storeAttachment($assignment, $request->file('file'));

        return (new AssignmentAttachmentResource($attachment))->response()->setStatusCode(201);
    }

    public function destroy(Assignment $assignment, AssignmentAttachment $attachment, AssignmentFileService $files): JsonResponse
    {
        Gate::authorize('update', $assignment);
        $files->deleteAttachment($attachment);

        return response()->json(status: 204);
    }
}
