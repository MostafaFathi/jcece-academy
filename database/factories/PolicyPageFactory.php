<?php

namespace Database\Factories;

use App\Models\PolicyPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PolicyPage>
 */
class PolicyPageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => 'privacy',
            'draft_ar' => 'نص تجريبي للسياسة',
            'draft_en' => 'Test policy text',
        ];
    }
}
