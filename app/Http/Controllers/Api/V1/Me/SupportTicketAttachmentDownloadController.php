<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Models\SupportTicketAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportTicketAttachmentDownloadController extends Controller
{
    public function __invoke(Request $request, int $attachment): StreamedResponse
    {
        $file = SupportTicketAttachment::query()->whereKey($attachment)->whereHas('message', fn ($query) => $query->where('is_internal', false)->whereHas('ticket', fn ($tickets) => $tickets->whereBelongsTo($request->user())))->firstOrFail();

        return Storage::disk($file->storage_disk)->download($file->storage_path, $file->original_filename);
    }
}
