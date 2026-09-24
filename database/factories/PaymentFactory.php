<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use App\PaymentMethod;
use App\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->awaitingPayment(),
            'transaction_id' => fake()->optional()->uuid(),
            'method' => PaymentMethod::BankTransfer,
            'gateway' => null,
            'amount' => 100,
            'currency' => 'JOD',
            'status' => PaymentStatus::PendingReview,
            'payment_proof' => 'payment-proofs/'.fake()->uuid().'.pdf',
            'gateway_response' => null,
            'paid_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Rejected,
            'rejection_reason' => fake()->sentence(),
        ]);
    }
}
