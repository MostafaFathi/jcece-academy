<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\PurchasableType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'purchasable_type' => PurchasableType::Course->value,
            'purchasable_id' => Course::factory(),
            'title' => fake()->sentence(3),
            'quantity' => 1,
            'unit_price' => 100,
            'discount_amount' => 0,
            'total' => 100,
            'access_duration_days' => fake()->optional()->numberBetween(30, 365),
        ];
    }

    public function forPackage(?Package $package = null): static
    {
        return $this->state(fn (): array => [
            'purchasable_type' => PurchasableType::Package->value,
            'purchasable_id' => $package ?? Package::factory(),
        ]);
    }
}
