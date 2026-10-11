<?php

namespace Tests\Feature\Api\V1\Admin;

use App\CourseLevel;
use App\Models\Category;
use App\Models\Course;
use App\Models\Package;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogImageApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_upload_replace_and_remove_category_image(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $this->post('/api/v1/admin/categories', [
            'name' => 'Engineering',
            'slug' => 'engineering',
            'image_upload' => UploadedFile::fake()->image('category.png'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $category = Category::query()->where('slug', 'engineering')->firstOrFail();
        $firstPath = $this->storedPath($category->image);
        Storage::disk('public')->assertExists($firstPath);

        $this->post("/api/v1/admin/categories/{$category->id}", [
            '_method' => 'PATCH',
            'image_upload' => UploadedFile::fake()->image('replacement.webp'),
        ], ['Accept' => 'application/json'])->assertOk();

        Storage::disk('public')->assertMissing($firstPath);
        $replacementPath = $this->storedPath($category->fresh()->image);
        Storage::disk('public')->assertExists($replacementPath);

        $this->patchJson("/api/v1/admin/categories/{$category->id}", ['image' => null])
            ->assertOk()->assertJsonPath('data.image', null);
        Storage::disk('public')->assertMissing($replacementPath);
    }

    public function test_admin_can_upload_course_and_package_thumbnails(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $category = Category::factory()->create();
        $instructor = User::factory()->create();
        $instructor->assignRole(RoleName::Instructor->value);

        $this->post('/api/v1/admin/courses', [
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Engineering 101',
            'slug' => 'engineering-101',
            'level' => CourseLevel::Beginner->value,
            'thumbnail_upload' => UploadedFile::fake()->image('course.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $course = Course::query()->where('slug', 'engineering-101')->firstOrFail();
        $coursePath = $this->storedPath($course->thumbnail);
        Storage::disk('public')->assertExists($coursePath);

        $this->post("/api/v1/admin/courses/{$course->id}", [
            '_method' => 'PATCH',
            'thumbnail_upload' => UploadedFile::fake()->image('course-replacement.webp'),
            'clear_learning_outcomes' => '1',
        ], ['Accept' => 'application/json'])->assertOk();

        Storage::disk('public')->assertMissing($coursePath);
        Storage::disk('public')->assertExists($this->storedPath($course->fresh()->thumbnail));

        $this->post('/api/v1/admin/packages', [
            'title' => 'Engineering Path',
            'slug' => 'engineering-path',
            'type' => 'package',
            'price' => '99.00',
            'thumbnail_upload' => UploadedFile::fake()->image('package.png'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $package = Package::query()->where('slug', 'engineering-path')->firstOrFail();
        $packagePath = $this->storedPath($package->thumbnail);
        Storage::disk('public')->assertExists($packagePath);

        $this->post("/api/v1/admin/packages/{$package->id}", [
            '_method' => 'PATCH',
            'thumbnail_upload' => UploadedFile::fake()->image('package-replacement.webp'),
        ], ['Accept' => 'application/json'])->assertOk();

        Storage::disk('public')->assertMissing($packagePath);
        Storage::disk('public')->assertExists($this->storedPath($package->fresh()->thumbnail));
    }

    public function test_invalid_image_is_rejected_without_storing_a_file(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $this->post('/api/v1/admin/categories', [
            'name' => 'Invalid image',
            'slug' => 'invalid-image',
            'image_upload' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('image_upload');

        $this->assertSame([], Storage::disk('public')->allFiles('catalog/categories'));
    }

    private function actingAsAdmin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($admin);
    }

    private function storedPath(string $url): string
    {
        return str_replace('/storage/', '', $url);
    }
}
