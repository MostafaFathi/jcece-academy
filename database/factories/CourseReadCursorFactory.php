<?php

namespace Database\Factories;

use App\Models\CourseConversation;
use App\Models\CourseReadCursor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CourseReadCursor> */
class CourseReadCursorFactory extends Factory
{
    public function definition(): array
    {
        return ['course_conversation_id' => CourseConversation::factory(), 'user_id' => User::factory(), 'last_read_message_id' => 0];
    }
}
