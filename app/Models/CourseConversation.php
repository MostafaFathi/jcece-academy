<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['course_id', 'instructor_id', 'student_id', 'kind', 'title', 'version'])]
class CourseConversation extends Model
{
    use HasFactory;

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CourseMessage::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(CourseConversationMember::class);
    }

    public function cursors(): HasMany
    {
        return $this->hasMany(CourseReadCursor::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CourseMessagingEvent::class);
    }

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }
}
