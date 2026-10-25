<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Course;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use App\RoleName;
use App\Services\PackageService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceCurrencySettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_change_future_currency_without_relabeling_existing_catalog_or_orders(): void
    {
        $course = Course::factory()->published()->create(['price' => '100.00', 'currency' => 'ILS']);
        $package = Package::factory()->published()->create(['price' => '50.00', 'currency' => 'ILS']);
        $coupon = Coupon::factory()->create(['currency' => 'ILS']);
        $order = Order::factory()->create(['currency' => 'ILS', 'total' => '100.00']);
        $this->admin();

        $this->putJson('/api/v1/admin/site-currency', ['currency' => 'JOD', 'scope' => 'future'])
            ->assertOk()->assertJsonPath('data.currency', 'JOD')->assertJsonPath('data.converted.courses', 0);
        $this->getJson('/api/v1/admin/site-content')->assertJsonPath('data.settings.currency', 'JOD');
        $this->getJson("/api/v1/courses/{$course->slug}")->assertJsonPath('data.currency', 'ILS');
        $this->getJson("/api/v1/packages/{$package->slug}")->assertJsonPath('data.currency', 'ILS');
        $this->assertSame('ILS', $coupon->fresh()->currency);
        $this->assertSame('ILS', $order->fresh()->currency);
        $this->assertSame('100.00', $course->fresh()->price);
    }

    public function test_full_change_converts_catalog_and_coupons_but_preserves_historical_order_snapshots(): void
    {
        $course = Course::factory()->published()->create(['price' => '100.00', 'compare_price' => '120.00', 'promotional_price' => '80.00', 'currency' => 'ILS']);
        $package = Package::factory()->published()->create(['price' => '50.00', 'currency' => 'ILS']);
        $coupon = Coupon::factory()->create(['discount_value' => '10.00', 'minimum_order_amount' => '20.00', 'currency' => 'ILS']);
        $percentage = Coupon::factory()->create(['discount_type' => 'percentage', 'discount_value' => '25.00', 'minimum_order_amount' => '30.00', 'currency' => 'ILS']);
        $order = Order::factory()->create(['currency' => 'ILS', 'total' => '100.00']);
        $this->admin();

        $this->putJson('/api/v1/admin/site-currency', ['currency' => 'JOD', 'scope' => 'all', 'rates' => ['ILS' => '0.25']])
            ->assertOk()->assertJsonPath('data.converted.courses', 1)->assertJsonPath('data.converted.packages', 1)->assertJsonPath('data.converted.coupons', 2);

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'currency' => 'JOD', 'price' => '25.00', 'compare_price' => '30.00', 'promotional_price' => '20.00']);
        $this->assertDatabaseHas('packages', ['id' => $package->id, 'currency' => 'JOD', 'price' => '12.50']);
        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'currency' => 'JOD', 'discount_value' => '2.50', 'minimum_order_amount' => '5.00']);
        $this->assertDatabaseHas('coupons', ['id' => $percentage->id, 'currency' => 'JOD', 'discount_value' => '25.00', 'minimum_order_amount' => '7.50']);
        $this->assertSame('ILS', $order->fresh()->currency);
        $this->assertSame('100.00', $order->fresh()->total);
    }

    public function test_new_products_use_the_changed_default_while_existing_products_keep_their_currency(): void
    {
        $existing = Package::factory()->published()->create(['currency' => 'ILS', 'price' => '40.00']);
        $this->admin();
        $this->putJson('/api/v1/admin/site-currency', ['currency' => 'JOD', 'scope' => 'future'])->assertOk();

        $new = app(PackageService::class)->create([
            'title' => 'New package', 'slug' => 'new-package', 'type' => 'package',
            'price' => '30.00', 'status' => 'draft',
        ]);

        $this->assertSame('JOD', $new->currency);
        $this->assertSame('ILS', $existing->fresh()->currency);
        $this->assertSame('40.00', $existing->fresh()->price);
    }

    public function test_missing_rate_rolls_back_the_entire_change(): void
    {
        $course = Course::factory()->create(['price' => '100.00', 'currency' => 'ILS']);
        $package = Package::factory()->create(['price' => '50.00', 'currency' => 'USD']);
        $this->admin();

        $this->putJson('/api/v1/admin/site-currency', ['currency' => 'JOD', 'scope' => 'all', 'rates' => ['ILS' => '0.25']])
            ->assertUnprocessable()->assertJsonValidationErrors('rates.USD');
        $this->assertSame('ILS', $course->fresh()->currency);
        $this->assertSame('100.00', $course->fresh()->price);
        $this->assertSame('USD', $package->fresh()->currency);
        $this->getJson('/api/v1/site-settings')->assertJsonPath('data.currency', 'ILS');
    }

    public function test_student_cannot_change_currency_and_mixed_currency_cart_is_rejected(): void
    {
        $ils = Course::factory()->published()->create(['currency' => 'ILS']);
        $jod = Course::factory()->published()->create(['currency' => 'JOD']);
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/v1/admin/site-currency', ['currency' => 'USD', 'scope' => 'future'])->assertForbidden();
        $this->postJson('/api/v1/me/cart/items', ['purchasable_type' => 'course', 'purchasable_id' => $ils->id])->assertOk();
        $this->postJson('/api/v1/me/cart/items', ['purchasable_type' => 'course', 'purchasable_id' => $jod->id])
            ->assertUnprocessable()->assertJsonValidationErrors('currency');
        $this->getJson('/api/v1/me/cart')->assertJsonPath('data.item_count', 1)->assertJsonPath('data.currency', 'ILS');
    }

    private function admin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($admin);
    }
}
