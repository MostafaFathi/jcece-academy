<?php

namespace Database\Seeders;

use App\Models\PolicyPage;
use Illuminate\Database\Seeder;

class PolicyPageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (PolicyPage::SLUGS as $slug) {
            PolicyPage::query()->firstOrCreate(['slug' => $slug]);
        }
    }
}
