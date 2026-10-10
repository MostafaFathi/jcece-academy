<?php

namespace Database\Factories;

use App\Models\CourseConversation;
use App\Models\CourseConversationMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CourseConversationMember> */
class CourseConversationMemberFactory extends Factory
{
    public function definition(): array
    {
        return ['course_conversation_id' => CourseConversation::factory(), 'user_id' => User::factory()];
    }
}
