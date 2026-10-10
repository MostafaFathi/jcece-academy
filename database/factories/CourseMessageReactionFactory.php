<?php

namespace Database\Factories;

use App\Models\CourseMessage;
use App\Models\CourseMessageReaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CourseMessageReaction> */
class CourseMessageReactionFactory extends Factory
{
    public function definition(): array
    {
        return ['course_message_id' => CourseMessage::factory(), 'user_id' => User::factory(), 'emoji' => '👍'];
    }
}
