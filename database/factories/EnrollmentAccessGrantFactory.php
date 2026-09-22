<?php

namespace Database\Factories;

use App\AccessGrantSource;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnrollmentAccessGrant>
 */
class EnrollmentAccessGrantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'source_type' => AccessGrantSource::Admin,
            'source_id' => null,
            'access_starts_at' => now()->subDay(),
            'access_expires_at' => now()->addMonth(),
            'revoked_at' => null,
            'revoked_by' => null,
            'revocation_reason' => null,
        ];
    }

    public function lifetime(): static
    {
        return $this->state(fn (): array => ['access_expires_at' => null]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'access_starts_at' => now()->subMonth(),
            'access_expires_at' => now()->subDay(),
        ]);
    }

    public function future(): static
    {
        return $this->state(fn (): array => [
            'access_starts_at' => now()->addDay(),
            'access_expires_at' => now()->addMonth(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'revoked_at' => now(),
            'revoked_by' => User::factory(),
            'revocation_reason' => fake()->sentence(),
        ]);
    }
}
