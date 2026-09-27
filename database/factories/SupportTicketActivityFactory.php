<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\SupportTicketActivity;
use App\Models\User;
use App\SupportTicketActivityType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicketActivity>
 */
class SupportTicketActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['support_ticket_id' => SupportTicket::factory(), 'actor_id' => User::factory(), 'actor_name_snapshot' => fake()->name(), 'event_type' => SupportTicketActivityType::Created];
    }
}
