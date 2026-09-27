<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportTicketAttachmentDownloadController extends Controller
{
    public function __invoke(SupportTicketAttachment $attachment): StreamedResponse
    {
        $attachment->load('message.ticket');
        Gate::authorize('viewAny', SupportTicket::class);

        return Storage::disk($attachment->storage_disk)->download($attachment->storage_path, $attachment->original_filename);
    }
}
