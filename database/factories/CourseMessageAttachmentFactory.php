<?php

namespace Database\Factories;

use App\Models\CourseMessage;
use App\Models\CourseMessageAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CourseMessageAttachment> */
class CourseMessageAttachmentFactory extends Factory
{
    public function definition(): array
    {
        return ['course_message_id' => CourseMessage::factory(), 'storage_path' => 'test/image.png', 'original_filename' => 'image.png', 'mime_type' => 'image/png', 'kind' => 'image', 'file_size' => 10];
    }
}
