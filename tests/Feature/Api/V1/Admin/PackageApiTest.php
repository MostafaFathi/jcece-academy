<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Package;
use App\Models\User;
use App\PackageStatus;
use App\PackageType;
use App\PermissionName;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PackageApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_creates_published_package_with_decimal_prices(): void
    {
        $this->actingAsRole(RoleName::Admin);

        $this->postJson('/api/v1/admin/packages', $this->validPayload([
            'status' => PackageStatus::Published->value,
            'price' => 299.50,
            'compare_price' => 399.75,
        ]))->assertCreated()
            ->assertJsonPath('data.slug', 'bim-professional-bundle')
            ->assertJsonPath('data.price', '299.50')
            ->assertJsonPath('data.compare_price', '399.75')
            ->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('packages', [
            'slug' => 'bim-professional-bundle',
            'price' => 299.50,
        ]);
        $this->assertNotNull(Package::where('slug', 'bim-professional-bundle')->firstOrFail()->published_at);
    }

    public function test_content_manager_can_update_and_soft_delete_package(): void
    {
        $this->actingAsRole(RoleName::ContentManager);
        $package = Package::factory()->create();

        $this->patchJson("/api/v1/admin/packages/{$package->id}", ['title' => 'Updated Package'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Package');

        $this->deleteJson("/api/v1/admin/packages/{$package->id}")->assertNoContent();
        $this->assertSoftDeleted($package);
    }

    public function test_student_cannot_manage_packages(): void
    {
        $this->actingAsRole(RoleName::Student);

        $this->postJson('/api/v1/admin/packages', $this->validPayload())->assertForbidden();
        $this->assertDatabaseMissing('packages', ['slug' => 'bim-professional-bundle']);
    }

    public function test_admin_package_api_requires_authentication(): void
    {
        $this->postJson('/api/v1/admin/packages', $this->validPayload())->assertUnauthorized();
    }

    public function test_publishing_requires_separate_permission(): void
    {
        Permission::findOrCreate(PermissionName::PackagesCreate->value);
        Permission::findOrCreate(PermissionName::PackagesPublish->value);
        $creator = User::factory()->create();
        $creator->givePermissionTo(PermissionName::PackagesCreate->value);
        Sanctum::actingAs($creator);

        $this->postJson('/api/v1/admin/packages', $this->validPayload([
            'status' => PackageStatus::Published->value,
        ]))->assertForbidden();

        $this->postJson('/api/v1/admin/packages', $this->validPayload([
            'slug' => 'draft-package',
            'status' => PackageStatus::Draft->value,
        ]))->assertCreated();
    }

    public function test_create_validates_required_fields_duration_and_prices(): void
    {
        $this->actingAsRole(RoleName::Admin);

        $this->postJson('/api/v1/admin/packages', [
            'title' => '',
            'slug' => 'invalid slug',
            'type' => 'invalid',
            'price' => -1,
            'compare_price' => -2,
            'access_duration_days' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'slug', 'type', 'price', 'compare_price', 'access_duration_days']);

        $this->postJson('/api/v1/admin/packages', $this->validPayload(['access_duration_days' => -1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('access_duration_days');
    }

    public function test_lifetime_and_positive_duration_are_both_supported(): void
    {
        $this->actingAsRole(RoleName::Admin);

        $this->postJson('/api/v1/admin/packages', $this->validPayload([
            'slug' => 'lifetime-path',
            'access_duration_days' => null,
        ]))->assertCreated()->assertJsonPath('data.is_lifetime', true);

        $this->postJson('/api/v1/admin/packages', $this->validPayload([
            'slug' => 'annual-bundle',
            'access_duration_days' => 365,
        ]))->assertCreated()->assertJsonPath('data.access_duration_days', 365);
    }

    public function test_update_rejects_existing_compare_price_below_new_price(): void
    {
        $this->actingAsRole(RoleName::Admin);
        $package = Package::factory()->create(['price' => 100, 'compare_price' => 150]);

        $this->patchJson("/api/v1/admin/packages/{$package->id}", ['price' => 200])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('compare_price');
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'title' => 'BIM Professional Bundle',
            'slug' => 'bim-professional-bundle',
            'type' => PackageType::Package->value,
            'price' => 299,
            ...$overrides,
        ];
    }

    private function actingAsRole(RoleName $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role->value);
        Sanctum::actingAs($user);

        return $user;
    }
}
