<?php

namespace App\Models;

use Database\Factories\AssignmentSubmissionFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assignment_submission_id', 'original_filename', 'storage_disk', 'storage_path', 'mime_type', 'file_size'])]
#[Hidden(['storage_disk', 'storage_path'])]
class AssignmentSubmissionFile extends Model
{
    /** @use HasFactory<AssignmentSubmissionFileFactory> */
    use HasFactory;

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssignmentSubmission::class, 'assignment_submission_id');
    }
}
