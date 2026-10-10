<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['course_message_id', 'user_id', 'emoji'])]
class CourseMessageReaction extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [];
    }
}
