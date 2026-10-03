<?php

namespace App\Services;

use App\Events\SupportTicketMessagePosted;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\SupportTicketStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SupportTicketMessageService
{
    public function __construct(private UploadedFileStorage $uploads) {}

    /** @param list<UploadedFile> $attachments */
    public function post(User $actor, SupportTicket $ticket, string $body, bool $internal, array $attachments = [], bool $applyWorkflow = true): SupportTicketMessage
    {
        $disk = (string) config('jcec.support.attachment_disk', 'local');
        $paths = [];
        try {
            $message = DB::transaction(function () use ($actor, $ticket, $body, $internal, $attachments, $disk, &$paths, $applyWorkflow): SupportTicketMessage {
                $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
                if ($locked->status === SupportTicketStatus::Closed) {
                    throw ValidationException::withMessages(['ticket' => 'A closed ticket must be reopened before a reply can be posted.']);
                }
                $message = $locked->messages()->create(['user_id' => $actor->id, 'body' => $body, 'is_internal' => $internal]);
                foreach ($attachments as $file) {
                    $directory = "support-tickets/{$locked->id}/messages/{$message->id}";
                    $path = $this->uploads->store($file, $directory, $disk, 'attachments');
                    $paths[] = $path;
                    $message->attachments()->create(['original_filename' => Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 255, ''), 'storage_disk' => $disk, 'storage_path' => $path, 'mime_type' => $file->getMimeType() ?? 'application/octet-stream', 'file_size' => $file->getSize()]);
                }
                if (! $internal) {
                    $locked->update(['last_reply_at' => now()]);

                    if ($applyWorkflow) {
                        app(SupportTicketService::class)->transitionForReply($actor, $locked->refresh(), $locked->user_id !== $actor->id);
                    }
                }

                return $message;
            });
        } catch (Throwable $exception) {
            if ($paths !== []) {
                Storage::disk($disk)->delete($paths);
            }
            throw $exception;
        }
        SupportTicketMessagePosted::dispatch($message);

        return $message->load(['user:id,name', 'attachments']);
    }
}
