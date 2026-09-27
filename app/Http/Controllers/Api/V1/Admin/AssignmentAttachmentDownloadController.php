<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssignmentAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentAttachmentDownloadController extends Controller
{
    public function __invoke(AssignmentAttachment $attachment): StreamedResponse
    {
        $attachment->load('assignment');
        Gate::authorize('downloadAttachment', $attachment->assignment);
        $disk = Storage::disk($attachment->storage_disk);
        abort_unless($disk->exists($attachment->storage_path), 404);

        return $disk->download($attachment->storage_path, $attachment->original_filename);
    }
}
