<?php

namespace App\Models;

use Database\Factories\PackageCourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['package_id', 'course_id', 'sort_order', 'is_required'])]
class PackageCourse extends Model
{
    /** @use HasFactory<PackageCourseFactory> */
    use HasFactory;

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }
}
