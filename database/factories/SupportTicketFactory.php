<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\User;
use App\SupportTicketCategory;
use App\SupportTicketPriority;
use App\SupportTicketStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_number' => 'JCEC-'.Str::upper((string) Str::ulid()),
            'user_id' => User::factory(),
            'subject' => fake()->sentence(6),
            'category' => fake()->randomElement(SupportTicketCategory::cases()),
            'priority' => SupportTicketPriority::Normal,
            'status' => SupportTicketStatus::Open,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (): array => ['status' => SupportTicketStatus::Closed, 'closed_at' => now()]);
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => ['status' => SupportTicketStatus::Resolved, 'resolved_at' => now()]);
    }
}
