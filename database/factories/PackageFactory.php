<?php

namespace Database\Factories;

use App\Models\Package;
use App\PackageStatus;
use App\PackageType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);
        $price = fake()->randomFloat(2, 0, 1000);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->paragraph(),
            'thumbnail' => fake()->optional()->imageUrl(),
            'type' => PackageType::Package,
            'price' => $price,
            'compare_price' => fake()->optional()->randomFloat(2, $price, 1500),
            'access_duration_days' => fake()->optional()->numberBetween(30, 365),
            'is_sequential' => false,
            'status' => PackageStatus::Draft,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => PackageStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }

    public function learningPath(): static
    {
        return $this->state(fn (): array => [
            'type' => PackageType::LearningPath,
            'is_sequential' => true,
        ]);
    }

    public function lifetime(): static
    {
        return $this->state(fn (): array => ['access_duration_days' => null]);
    }
}
