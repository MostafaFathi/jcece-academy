<?php

namespace Database\Factories;

use App\Models\CertificateApprovalRequest;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificateApprovalRequest>
 */
class CertificateApprovalRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'user_id' => User::factory(),
            'requirements_version' => 1,
            'status' => CertificateApprovalRequest::Pending,
            'requested_at' => now(),
        ];
    }
}
