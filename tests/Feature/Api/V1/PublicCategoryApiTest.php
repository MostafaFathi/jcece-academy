<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PublicCategoryApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_only_active_categories_in_hierarchy_order(): void
    {
        $parent = Category::factory()->create(['name' => 'Management', 'slug' => 'management', 'sort_order' => 2]);
        Category::factory()->for($parent, 'parent')->create(['name' => 'Leadership', 'slug' => 'leadership']);
        Category::factory()->inactive()->create(['name' => 'Hidden', 'slug' => 'hidden']);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'management')
            ->assertJsonPath('data.0.children.0.slug', 'leadership')
            ->assertJsonMissing(['slug' => 'hidden']);
    }

    public function test_returns_404_for_inactive_category_details(): void
    {
        $category = Category::factory()->inactive()->create();

        $this->getJson("/api/v1/categories/{$category->id}")
            ->assertNotFound();
    }
}
