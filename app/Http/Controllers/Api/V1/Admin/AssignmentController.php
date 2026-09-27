<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\AssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreAssignmentRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAssignmentRequest;
use App\Http\Resources\Api\V1\AdminAssignmentResource;
use App\Models\Assignment;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AssignmentController extends Controller
{
    public function index(Course $course): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Assignment::class);

        return AdminAssignmentResource::collection($course->assignments()->with('attachments')->get());
    }

    public function store(StoreAssignmentRequest $request, Course $course): JsonResponse
    {
        Gate::authorize('create', Assignment::class);
        $assignment = $course->assignments()->create($request->validated() + ['status' => AssignmentStatus::Draft]);

        return (new AdminAssignmentResource($assignment->load('attachments')))->response()->setStatusCode(201);
    }

    public function show(Course $course, Assignment $assignment): AdminAssignmentResource
    {
        Gate::authorize('view', $assignment);

        return new AdminAssignmentResource($assignment->load('attachments'));
    }

    public function update(UpdateAssignmentRequest $request, Course $course, Assignment $assignment): AdminAssignmentResource
    {
        Gate::authorize('update', $assignment);
        $assignment->update($request->validated());

        return new AdminAssignmentResource($assignment->refresh()->load('attachments'));
    }

    public function destroy(Course $course, Assignment $assignment): AdminAssignmentResource
    {
        Gate::authorize('delete', $assignment);
        $assignment->update(['status' => AssignmentStatus::Archived]);

        return new AdminAssignmentResource($assignment->refresh()->load('attachments'));
    }
}
