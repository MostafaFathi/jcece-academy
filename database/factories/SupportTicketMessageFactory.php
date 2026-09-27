<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicketMessage>
 */
class SupportTicketMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['support_ticket_id' => SupportTicket::factory(), 'user_id' => User::factory(), 'body' => fake()->paragraph(), 'is_internal' => false];
    }

    public function internal(): static
    {
        return $this->state(fn (): array => ['is_internal' => true]);
    }
}
