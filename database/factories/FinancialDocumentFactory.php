<?php

namespace Database\Factories;

use App\Models\FinancialDocument;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FinancialDocument>
 */
class FinancialDocumentFactory extends Factory
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
            'source_key' => 'order:'.Str::ulid(),
            'document_number' => 'JCEC-R-'.Str::ulid(),
            'kind' => 'purchase',
            'locale' => 'ar',
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'currency' => 'JOD',
            'amount' => '100.00',
            'snapshot' => ['order_number' => 'TEST'],
            'pdf_disk' => 'local',
            'pdf_path' => 'financial-documents/test.pdf',
            'pdf_sha256' => str_repeat('0', 64),
            'issued_at' => now(),
        ];
    }
}
