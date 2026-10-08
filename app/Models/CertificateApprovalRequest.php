<?php

namespace App\Models;

use Database\Factories\CertificateApprovalRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['course_id', 'user_id', 'requirements_version', 'status', 'requested_at', 'approved_by', 'approver_name_snapshot', 'approved_at'])]
class CertificateApprovalRequest extends Model
{
    public const Pending = 'pending';

    public const Approved = 'approved';

    /** @use HasFactory<CertificateApprovalRequestFactory> */
    use HasFactory;

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'approved_at' => 'datetime'];
    }
}
