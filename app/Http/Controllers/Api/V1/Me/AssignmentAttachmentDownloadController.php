<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Models\AssignmentAttachment;
use App\Services\AssignmentAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentAttachmentDownloadController extends Controller
{
    public function __invoke(Request $request, AssignmentAttachment $attachment, AssignmentAccessService $access): StreamedResponse
    {
        $attachment->load('assignment.course');
        $access->requireAvailable($request->user(), $attachment->assignment);
        $disk = Storage::disk($attachment->storage_disk);
        abort_unless($disk->exists($attachment->storage_path), 404);

        return $disk->download($attachment->storage_path, $attachment->original_filename);
    }
}
