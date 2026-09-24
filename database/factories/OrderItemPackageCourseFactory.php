<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\OrderItem;
use App\Models\OrderItemPackageCourse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItemPackageCourse>
 */
class OrderItemPackageCourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory()->forPackage(),
            'course_id' => Course::factory(),
            'course_title' => fake()->sentence(3),
            'sort_order' => 0,
        ];
    }
}
