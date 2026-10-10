<?php

namespace Database\Factories;

use App\Models\CourseConversation;
use App\Models\CourseMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CourseMessage> */
class CourseMessageFactory extends Factory
{
    public function definition(): array
    {
        return ['course_conversation_id' => CourseConversation::factory(), 'user_id' => User::factory(), 'body' => fake()->sentence(), 'client_id' => fake()->uuid()];
    }
}
