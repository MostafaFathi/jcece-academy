<?php

namespace App\Models;

use Database\Factories\AssignmentAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assignment_id', 'original_filename', 'storage_disk', 'storage_path', 'mime_type', 'file_size'])]
#[Hidden(['storage_disk', 'storage_path'])]
class AssignmentAttachment extends Model
{
    /** @use HasFactory<AssignmentAttachmentFactory> */
    use HasFactory;

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }
}
