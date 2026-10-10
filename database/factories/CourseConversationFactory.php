<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CourseConversation> */
class CourseConversationFactory extends Factory
{
    public function definition(): array
    {
        return ['course_id' => Course::factory(), 'instructor_id' => User::factory(), 'kind' => 'all', 'title' => fake()->sentence(3)];
    }
}
