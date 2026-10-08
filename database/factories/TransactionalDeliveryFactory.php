<?php

namespace Database\Factories;

use App\Models\TransactionalDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TransactionalDelivery>
 */
class TransactionalDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_key' => 'test:'.Str::ulid(),
            'message_type' => 'welcome',
            'recipient_email' => fake()->safeEmail(),
            'locale' => 'ar',
            'subject_type' => 'user',
            'subject_id' => 1,
            'payload' => ['name' => fake()->name()],
            'status' => 'queued',
            'queued_at' => now(),
        ];
    }
}
