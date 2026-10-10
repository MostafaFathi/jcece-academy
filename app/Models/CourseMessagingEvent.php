<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['course_conversation_id', 'version', 'kind', 'subject_id', 'created_at'])]
class CourseMessagingEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['version' => 'integer', 'created_at' => 'datetime'];
    }
}
