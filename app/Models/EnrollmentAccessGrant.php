<?php

namespace App\Models;

use App\AccessGrantSource;
use Carbon\CarbonInterface;
use Database\Factories\EnrollmentAccessGrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['enrollment_id', 'source_type', 'source_id', 'access_starts_at', 'access_expires_at', 'revoked_at', 'revoked_by', 'revocation_reason'])]
class EnrollmentAccessGrant extends Model
{
    /** @use HasFactory<EnrollmentAccessGrantFactory> */
    use HasFactory;

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isCurrentlyValid(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->revoked_at === null
            && $this->access_starts_at->lte($at)
            && ($this->access_expires_at === null || $this->access_expires_at->gt($at));
    }

    #[Scope]
    protected function currentlyValid(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query
            ->whereNull('revoked_at')
            ->where('access_starts_at', '<=', $at)
            ->where(function (Builder $query) use ($at): void {
                $query->whereNull('access_expires_at')
                    ->orWhere('access_expires_at', '>', $at);
            });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'source_type' => AccessGrantSource::class,
            'access_starts_at' => 'datetime',
            'access_expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
