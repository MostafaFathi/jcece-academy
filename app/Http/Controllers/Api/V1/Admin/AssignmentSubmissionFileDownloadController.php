<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmissionFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentSubmissionFileDownloadController extends Controller
{
    public function __invoke(AssignmentSubmissionFile $file): StreamedResponse
    {
        $file->load('submission.assignment.course');
        Gate::authorize('review', $file->submission);
        $disk = Storage::disk($file->storage_disk);
        abort_unless($disk->exists($file->storage_path), 404);

        return $disk->download($file->storage_path, $file->original_filename);
    }
}
