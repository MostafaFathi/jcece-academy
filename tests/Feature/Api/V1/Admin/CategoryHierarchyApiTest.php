<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Category;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryHierarchyApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_422_when_category_is_parented_to_itself_or_a_descendant(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);
        $grandchild = Category::factory()->create(['parent_id' => $child->id]);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/categories/{$root->id}", ['parent_id' => $root->id])
            ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        $this->patchJson("/api/v1/admin/categories/{$root->id}", ['parent_id' => $grandchild->id])
            ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        $this->assertNull($root->fresh()->parent_id);
    }

    public function test_valid_reparenting_and_detachment_remain_supported(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $first = Category::factory()->create();
        $second = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $first->id]);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/categories/{$child->id}", ['parent_id' => $second->id])
            ->assertOk()->assertJsonPath('data.parent_id', $second->id);
        $this->patchJson("/api/v1/admin/categories/{$child->id}", ['parent_id' => null])
            ->assertOk()->assertJsonPath('data.parent_id', null);
        $this->assertNull($child->fresh()->parent_id);
    }
}
