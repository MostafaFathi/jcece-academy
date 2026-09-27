<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\AssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AdminAssignmentResource;
use App\Models\Assignment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssignmentPublicationController extends Controller
{
    public function store(Assignment $assignment): AdminAssignmentResource
    {
        Gate::authorize('publish', $assignment);

        if ($assignment->status === AssignmentStatus::Archived) {
            throw ValidationException::withMessages(['assignment' => 'An archived assignment cannot be published.']);
        }

        $assignment->update(['status' => AssignmentStatus::Published]);

        return new AdminAssignmentResource($assignment->refresh()->load('attachments'));
    }

    public function destroy(Assignment $assignment): AdminAssignmentResource
    {
        Gate::authorize('publish', $assignment);

        if ($assignment->status !== AssignmentStatus::Archived) {
            $assignment->update(['status' => AssignmentStatus::Draft]);
        }

        return new AdminAssignmentResource($assignment->refresh()->load('attachments'));
    }
}
