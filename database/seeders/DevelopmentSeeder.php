<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $this->call([
            StaffSeeder::class,
            CatalogSeeder::class,
            SitePageSeeder::class,
            SiteFaqSeeder::class,
        ]);
    }
}
