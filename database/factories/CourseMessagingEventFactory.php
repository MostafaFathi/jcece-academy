<?php

namespace Database\Factories;

use App\Models\CourseConversation;
use App\Models\CourseMessagingEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CourseMessagingEvent> */
class CourseMessagingEventFactory extends Factory
{
    public function definition(): array
    {
        return ['course_conversation_id' => CourseConversation::factory(), 'version' => 1, 'kind' => 'message', 'created_at' => now()];
    }
}
