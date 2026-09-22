<?php

namespace App\Models;

use Database\Factories\InstructorProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'job_title', 'short_bio', 'bio', 'years_experience', 'specialties', 'linkedin_url', 'facebook_url', 'instagram_url', 'website_url', 'is_featured'])]
class InstructorProfile extends Model
{
    /** @use HasFactory<InstructorProfileFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'specialties' => 'array',
            'is_featured' => 'boolean',
        ];
    }
}
