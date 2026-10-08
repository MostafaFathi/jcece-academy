<?php

namespace Database\Seeders;

use App\Models\SitePage;
use Illuminate\Database\Seeder;

class SitePageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (SitePage::SLUGS as $slug) {
            SitePage::query()->firstOrCreate(['slug' => $slug]);
        }
    }
}
