<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CouponManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_normalizes_code_and_rejects_duplicate_normalized_code(): void
    {
        $this->authenticate(RoleName::Admin);
        $this->postJson('/api/v1/admin/coupons', $this->payload(['code' => ' save10 ']))->assertCreated()->assertJsonPath('code', 'SAVE10');
        $this->postJson('/api/v1/admin/coupons', $this->payload(['code' => 'save10']))->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertDatabaseCount('coupons', 1);
    }

    public function test_sales_support_cannot_view_or_manage_coupons(): void
    {
        $this->authenticate(RoleName::SalesSupport);
        $this->getJson('/api/v1/admin/coupons')->assertForbidden();
        $this->postJson('/api/v1/admin/coupons', $this->payload())->assertForbidden();
    }

    public function test_admin_can_filter_coupon_list_by_activation_state(): void
    {
        $this->authenticate(RoleName::Admin);
        Coupon::factory()->create(['code' => 'ACTIVE', 'is_active' => true]);
        Coupon::factory()->create(['code' => 'INACTIVE', 'is_active' => false]);

        $this->getJson('/api/v1/admin/coupons?active=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'ACTIVE');
        $this->getJson('/api/v1/admin/coupons?active=0')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'INACTIVE');
    }

    public function test_coupon_update_does_not_rewrite_historical_order_snapshot(): void
    {
        $this->authenticate(RoleName::Admin);
        $coupon = Coupon::factory()->create(['code' => 'HISTORY']);
        $order = Order::factory()->create(['coupon_id' => $coupon->id, 'coupon_code_snapshot' => 'HISTORY', 'coupon_type_snapshot' => 'fixed', 'coupon_value_snapshot' => '10.00']);
        $this->patchJson('/api/v1/admin/coupons/'.$coupon->id, $this->payload(['code' => 'CHANGED', 'is_active' => false]))->assertOk();
        $this->assertSame('HISTORY', $order->fresh()->coupon_code_snapshot);
        $this->assertSame('10.00', $order->fresh()->coupon_value_snapshot);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [...['code' => 'SAVE10', 'is_active' => true, 'discount_type' => 'percentage', 'discount_value' => '10.00', 'applies_to' => 'all', 'product_ids' => []], ...$overrides];
    }

    private function authenticate(RoleName $role): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role->value);
        Sanctum::actingAs($user);
    }
}
