<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Package;
use App\Models\PackageCourse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackageCourse>
 */
class PackageCourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'package_id' => Package::factory(),
            'course_id' => Course::factory(),
            'sort_order' => 0,
            'is_required' => true,
        ];
    }

    public function optional(): static
    {
        return $this->state(fn (): array => ['is_required' => false]);
    }
}
