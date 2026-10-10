<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['course_conversation_id', 'user_id', 'removed_at'])]
class CourseConversationMember extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['removed_at' => 'datetime'];
    }
}
