<?php

namespace Database\Factories;

use App\CertificateStatus;
use App\Models\Certificate;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => fn (array $attributes): int => Enrollment::query()->findOrFail($attributes['enrollment_id'])->user_id,
            'course_id' => fn (array $attributes): int => Enrollment::query()->findOrFail($attributes['enrollment_id'])->course_id,
            'enrollment_id' => Enrollment::factory(),
            'certificate_number' => 'JCEC-'.now()->format('Y').'-'.Str::upper(Str::random(12)),
            'verification_token' => Str::random(64),
            'active_key' => fn (array $attributes): string => "{$attributes['user_id']}:{$attributes['course_id']}",
            'student_name_snapshot' => fake()->name(),
            'course_title_snapshot' => fake()->sentence(4),
            'instructor_name_snapshot' => fake()->name(),
            'issued_at' => now(),
            'completed_at' => now()->subDay(),
            'status' => CertificateStatus::Issued,
            'pdf_disk' => 'local',
            'pdf_path' => 'certificates/test.pdf',
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'status' => CertificateStatus::Revoked,
            'active_key' => null,
            'revoked_at' => now(),
            'revocation_reason' => 'Administrative revocation.',
        ]);
    }
}
