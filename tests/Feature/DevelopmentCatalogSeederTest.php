<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Package;
use App\Models\PolicyPage;
use App\Models\SiteFaq;
use App\Models\User;
use App\RoleName;
use Database\Seeders\DevelopmentSeeder;
use Database\Seeders\PolicyPageSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevelopmentCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_catalog_seeds_staff_without_students_and_arabic_products(): void
    {
        $this->seed([RolesAndPermissionsSeeder::class, PolicyPageSeeder::class, DevelopmentSeeder::class]);

        $this->assertSame(8, User::query()->count());
        foreach ([RoleName::Admin, RoleName::Instructor, RoleName::ContentManager, RoleName::SalesSupport] as $role) {
            $this->assertSame(2, User::query()->role($role->value)->count());
        }
        $this->assertSame(0, User::query()->role(RoleName::Student->value)->count());
        $this->assertSame(3, Category::query()->count());
        $this->assertSame(6, Course::query()->count());
        $this->assertSame(12, Lesson::query()->count());
        $this->assertSame(3, Package::query()->count());
        $this->assertTrue(Course::query()->where('title', 'like', '%BIM%')->exists());
        $this->assertSame(0, Course::query()->where('certificate_enabled', true)->count());
        $this->assertSame(3, SiteFaq::query()->count());
        $this->assertSame(0, SiteFaq::query()->where('is_active', true)->count());
        $this->assertSame(0, PolicyPage::query()->whereNotNull('published_at')->count());
        $this->assertSame('ILS', config('jcec.commerce.currency'));

        $this->seed(DevelopmentSeeder::class);

        $this->assertSame(8, User::query()->count());
        $this->assertSame(6, Course::query()->count());
        $this->assertSame(12, Lesson::query()->count());
        $this->assertSame(3, Package::query()->count());
    }
}
