<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['course_conversation_id', 'user_id', 'last_read_message_id'])]
class CourseReadCursor extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['last_read_message_id' => 'integer'];
    }
}
