<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Category;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_when_category_create_has_no_authentication(): void
    {
        $this->postJson('/api/v1/admin/categories', [])->assertUnauthorized();
    }

    public function test_authorized_admin_creates_category_and_returns_201(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/categories', [
            'name' => 'Technology',
            'slug' => 'technology',
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'technology');

        $this->assertDatabaseHas('categories', ['name' => 'Technology', 'slug' => 'technology']);
    }

    public function test_category_create_returns_422_for_invalid_payload(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        Category::factory()->create(['slug' => 'existing']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/categories', ['name' => '', 'slug' => 'existing'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'slug']);
    }
}
