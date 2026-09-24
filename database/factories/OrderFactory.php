<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_number' => 'JCEC-'.fake()->unique()->numerify('##########'),
            'idempotency_key' => fake()->uuid(),
            'status' => OrderStatus::Pending,
            'currency' => 'JOD',
            'subtotal' => 100,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => 100,
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->phoneNumber(),
            'notes' => fake()->optional()->sentence(),
            'placed_at' => now(),
            'paid_at' => null,
        ];
    }

    public function awaitingPayment(): static
    {
        return $this->state(fn (): array => ['status' => OrderStatus::AwaitingPayment]);
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
