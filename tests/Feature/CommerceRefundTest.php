<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\EnrollmentAccessGrant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemPackageCourse;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\OrderStatus;
use App\RoleName;
use App\Services\OrderAccessProvisioningService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceRefundTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_full_manual_refund_revokes_only_purchase_grants_and_preserves_other_access(): void
    {
        $admin = $this->authenticate(RoleName::Admin);
        [$order, $items] = $this->completedOrder();
        EnrollmentAccessGrant::factory()->for($order->items->first()->purchasable->enrollments()->first())->lifetime()->create();
        $refundId = $this->postJson("/api/v1/admin/orders/{$order->id}/refunds", $this->refundPayload('100.00', 'full'))->assertCreated()->json('id');
        $this->postJson("/api/v1/admin/orders/{$order->id}/refunds/{$refundId}/complete")->assertOk()->assertJsonPath('status', 'completed');
        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
        $this->assertDatabaseHas('enrollment_access_grants', ['source_id' => $items[0]->id, 'source_type' => 'direct_purchase', 'revoked_by' => $admin->id]);
        $this->assertDatabaseHas('enrollment_access_grants', ['source_type' => 'admin', 'revoked_at' => null]);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'paid']);
    }

    public function test_partial_financial_refund_keeps_access_and_rejected_refund_releases_balance(): void
    {
        $this->authenticate(RoleName::Admin);
        [$order, $items] = $this->completedOrder();
        $first = $this->postJson("/api/v1/admin/orders/{$order->id}/refunds", $this->refundPayload('20.00'))->assertCreated()->json('id');
        $this->postJson("/api/v1/admin/orders/{$order->id}/refunds/{$first}/complete")->assertOk();
        $this->assertDatabaseHas('enrollment_access_grants', ['source_id' => $items[0]->id, 'revoked_at' => null]);
        $this->getJson("/api/v1/admin/orders/{$order->id}")->assertJsonPath('data.refund_balance.refunded', '20.00')->assertJsonPath('data.refund_balance.refundable', '80.00');
        $second = $this->postJson("/api/v1/admin/orders/{$order->id}/refunds", $this->refundPayload('70.00'))->assertCreated()->json('id');
        $this->postJson("/api/v1/admin/orders/{$order->id}/refunds/{$second}/reject")->assertOk();
        $this->getJson("/api/v1/admin/orders/{$order->id}")->assertJsonPath('data.refund_balance.refundable', '80.00');
    }

    public function test_pending_refund_cannot_prematurely_reserve_the_final_balance_or_revoke_access(): void
    {
        $this->authenticate(RoleName::Admin);
        [$order, $items] = $this->completedOrder();
        $first = $this->postJson("/api/v1/admin/orders/{$order->id}/refunds", $this->refundPayload('20.00'))->assertCreated()->json('id');
        $this->postJson("/api/v1/admin/orders/{$order->id}/refunds", $this->refundPayload('80.00', 'full'))->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->postJson("/api/v1/admin/orders/{$order->id}/refunds", $this->refundPayload('80.00'))->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->assertDatabaseHas('enrollment_access_grants', ['source_id' => $items[0]->id, 'revoked_at' => null]);
        $this->postJson("/api/v1/admin/orders/{$order->id}/refunds/{$first}/complete")->assertOk();
        $last = $this->postJson("/api/v1/admin/orders/{$order->id}/refunds", $this->refundPayload('80.00', 'full'))->assertCreated()->json('id');
        $this->postJson("/api/v1/admin/orders/{$order->id}/refunds/{$last}/complete")->assertOk();
        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
        $this->assertDatabaseMissing('enrollment_access_grants', ['source_id' => $items[0]->id, 'revoked_at' => null]);
    }

    public function test_item_refund_revokes_only_selected_order_item_and_over_refund_is_rejected(): void
    {
        $this->authenticate(RoleName::Admin);
        [$order, $items] = $this->completedOrder();
        $this->postJson("/api/v1/admin/orders/{$order->id}/refunds", $this->refundPayload('101.00'))->assertUnprocessable()->assertJsonValidationErrors('amount');
        $payload = $this->refundPayload('50.00', 'items');
        $payload['order_item_ids'] = [$items[0]->id];
        $refundId = $this->postJson("/api/v1/admin/orders/{$order->id}/refunds", $payload)->assertCreated()->json('id');
        $this->postJson("/api/v1/admin/orders/{$order->id}/refunds/{$refundId}/complete")->assertOk();
        $this->assertDatabaseMissing('enrollment_access_grants', ['source_id' => $items[0]->id, 'revoked_at' => null]);
        $this->assertDatabaseHas('enrollment_access_grants', ['source_id' => $items[1]->id, 'revoked_at' => null]);
    }

    public function test_sales_support_and_student_cannot_manage_refunds_or_read_internal_note(): void
    {
        $this->authenticate(RoleName::Admin);
        [$order] = $this->completedOrder();
        $this->postJson("/api/v1/admin/orders/{$order->id}/refunds", [...$this->refundPayload('10.00'), 'internal_note' => 'Private staff note'])->assertCreated();
        $this->authenticate(RoleName::SalesSupport);
        $this->postJson("/api/v1/admin/orders/{$order->id}/refunds", $this->refundPayload('10.00'))->assertForbidden();
        $this->getJson("/api/v1/admin/orders/{$order->id}")->assertOk()->assertDontSee('Private staff note');
        Sanctum::actingAs($order->user);
        $this->postJson("/api/v1/admin/orders/{$order->id}/refunds", $this->refundPayload('10.00'))->assertForbidden();
        $this->getJson("/api/v1/me/orders/{$order->id}")->assertOk()->assertDontSee('Private staff note');
    }

    public function test_full_package_refund_preserves_course_snapshot_and_historical_certificate(): void
    {
        $this->authenticate(RoleName::Admin);
        $student = User::factory()->create();
        $order = Order::factory()->paid()->for($student)->create(['subtotal' => '80.00', 'total' => '80.00']);
        $package = Package::factory()->create();
        $item = OrderItem::factory()->for($order)->forPackage($package)->create(['unit_price' => '80.00', 'total' => '80.00', 'sequential_completion_percentage' => '70.00']);
        $first = Course::factory()->create();
        $second = Course::factory()->create();
        OrderItemPackageCourse::factory()->for($item)->for($first)->create(['sort_order' => 0, 'course_title' => 'Original first']);
        OrderItemPackageCourse::factory()->for($item)->for($second)->create(['sort_order' => 1, 'course_title' => 'Original second']);
        Payment::factory()->paid()->for($order)->create(['amount' => '80.00']);
        app(OrderAccessProvisioningService::class)->provision($order);
        $certificate = Certificate::factory()->create(['enrollment_id' => $first->enrollments()->where('user_id', $student->id)->firstOrFail()->id]);
        $refundId = $this->postJson("/api/v1/admin/orders/{$order->id}/refunds", $this->refundPayload('80.00', 'full'))->assertCreated()->json('id');
        $this->postJson("/api/v1/admin/orders/{$order->id}/refunds/{$refundId}/complete")->assertOk();
        $this->assertDatabaseCount('order_item_package_courses', 2);
        $this->assertDatabaseHas('order_item_package_courses', ['order_item_id' => $item->id, 'course_title' => 'Original first']);
        $this->assertDatabaseCount('enrollment_access_grants', 2);
        $this->assertSame(2, EnrollmentAccessGrant::query()->where('source_id', $item->id)->whereNotNull('revoked_at')->count());
        $this->assertModelExists($certificate);
    }

    public function test_completed_refund_never_restores_coupon_usage(): void
    {
        $this->authenticate(RoleName::Admin);
        [$order] = $this->completedOrder();
        $coupon = Coupon::factory()->create(['code' => 'USED', 'usage_limit' => 1]);
        $order->update(['coupon_id' => $coupon->id, 'coupon_code_snapshot' => 'USED', 'coupon_type_snapshot' => 'fixed', 'coupon_value_snapshot' => '10.00']);
        $coupon->redemptions()->create(['order_id' => $order->id, 'user_id' => $order->user_id, 'status' => 'consumed', 'consumed_at' => now()]);
        $refundId = $this->postJson("/api/v1/admin/orders/{$order->id}/refunds", $this->refundPayload('100.00', 'full'))->assertCreated()->json('id');
        $this->postJson("/api/v1/admin/orders/{$order->id}/refunds/{$refundId}/complete")->assertOk();
        $this->assertDatabaseHas('coupon_redemptions', ['coupon_id' => $coupon->id, 'order_id' => $order->id, 'status' => 'consumed']);
    }

    /** @return array{Order, array<int, OrderItem>} */
    private function completedOrder(): array
    {
        $student = User::factory()->create();
        $order = Order::factory()->paid()->for($student)->create(['subtotal' => '100.00', 'total' => '100.00']);
        $items = [];
        foreach (['First', 'Second'] as $title) {
            $course = Course::factory()->create(['title' => $title]);
            $items[] = OrderItem::factory()->for($order)->create(['purchasable_id' => $course->id, 'title' => $title, 'unit_price' => '50.00', 'total' => '50.00']);
        }
        Payment::factory()->paid()->for($order)->create(['amount' => '100.00']);
        app(OrderAccessProvisioningService::class)->provision($order);

        return [$order->refresh()->load('items.purchasable', 'user'), $items];
    }

    /** @return array<string, mixed> */
    private function refundPayload(string $amount, string $effect = 'none'): array
    {
        return ['amount' => $amount, 'reason' => 'customer_request', 'access_effect' => $effect, 'order_item_ids' => []];
    }

    private function authenticate(RoleName $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role->value);
        Sanctum::actingAs($user);

        return $user;
    }
}
