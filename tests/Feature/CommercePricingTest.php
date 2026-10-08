<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\OrderStatus;
use App\Services\CommercePricingService;
use App\Services\OrderStatusService;
use App\Services\PaymentReviewService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommercePricingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_promotion_uses_start_inclusive_and_end_exclusive_boundaries(): void
    {
        $course = Course::factory()->create(['price' => '100.00', 'promotional_price' => '80.00', 'discount_starts_at' => '2026-10-05 10:00:00', 'discount_ends_at' => '2026-10-06 10:00:00']);
        $pricing = app(CommercePricingService::class);
        $this->assertSame('100.00', $pricing->product($course, CarbonImmutable::parse('2026-10-05 09:59:59'))['active_price']);
        $this->assertSame('80.00', $pricing->product($course, CarbonImmutable::parse('2026-10-05 10:00:00'))['active_price']);
        $this->assertSame('80.00', $pricing->product($course, CarbonImmutable::parse('2026-10-06 09:59:59'))['active_price']);
        $this->assertSame('100.00', $pricing->product($course, CarbonImmutable::parse('2026-10-06 10:00:00'))['active_price']);
    }

    public function test_coupon_applies_after_promotion_and_snapshots_order(): void
    {
        $user = $this->student();
        $course = $this->cartCourse($user, ['price' => '100.00', 'promotional_price' => '80.00', 'discount_starts_at' => now()->subHour(), 'discount_ends_at' => now()->addHour()]);
        Coupon::factory()->create(['code' => 'SAVE25', 'discount_type' => 'percentage', 'discount_value' => '25.00']);
        $this->putJson('/api/v1/me/cart/coupon', ['code' => ' save25 '])->assertOk()->assertJsonPath('data.estimated_total', '60.00');
        $response = $this->postJson('/api/v1/me/checkout', $this->checkout('60.00', 'SAVE25'))->assertCreated()
            ->assertJsonPath('data.subtotal', '100.00')->assertJsonPath('data.discount_total', '40.00')
            ->assertJsonPath('data.items.0.promotional_discount_amount', '20.00')->assertJsonPath('data.items.0.coupon_discount_amount', '20.00');
        $course->update(['price' => '500.00']);
        $this->getJson('/api/v1/me/orders/'.$response->json('data.id'))->assertJsonPath('data.total', '60.00');
        $this->assertDatabaseHas('coupon_redemptions', ['order_id' => $response->json('data.id'), 'status' => 'reserved']);
    }

    public function test_fixed_coupon_caps_at_zero_and_provisions_free_order(): void
    {
        $user = $this->student();
        $course = $this->cartCourse($user, ['price' => '15.00']);
        Coupon::factory()->create(['code' => 'FREE', 'discount_value' => '50.00']);
        $this->putJson('/api/v1/me/cart/coupon', ['code' => 'free'])->assertJsonPath('data.estimated_total', '0.00');
        $response = $this->postJson('/api/v1/me/checkout', $this->checkout('0.00', 'FREE'))->assertCreated()->assertJsonPath('data.status', 'completed');
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('coupon_redemptions', ['order_id' => $response->json('data.id'), 'status' => 'consumed']);
        $this->assertDatabaseHas('enrollments', ['user_id' => $user->id, 'course_id' => $course->id]);
    }

    public function test_limited_coupon_reservation_blocks_second_checkout_until_cancellation(): void
    {
        $first = $this->student();
        $this->cartCourse($first, ['price' => '100.00']);
        Coupon::factory()->create(['code' => 'ONE', 'usage_limit' => 1]);
        $this->putJson('/api/v1/me/cart/coupon', ['code' => 'one'])->assertOk();
        $order = $this->postJson('/api/v1/me/checkout', $this->checkout('90.00', 'ONE'))->assertCreated()->json('data.id');
        $second = $this->student();
        $this->cartCourse($second, ['price' => '100.00']);
        $this->putJson('/api/v1/me/cart/coupon', ['code' => 'ONE'])->assertUnprocessable();
        app(OrderStatusService::class)->transition(Order::findOrFail($order), OrderStatus::Cancelled);
        $this->putJson('/api/v1/me/cart/coupon', ['code' => 'ONE'])->assertOk();
    }

    public function test_checkout_rejects_stale_price_with_structured_conflict(): void
    {
        $user = $this->student();
        $course = $this->cartCourse($user, ['price' => '100.00']);
        $course->update(['price' => '120.00']);
        $this->postJson('/api/v1/me/checkout', $this->checkout('100.00'))->assertStatus(409)->assertJsonPath('code', 'pricing_changed')->assertJsonPath('pricing.total', '120.00');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_coupon_rejects_inactive_expired_minimum_and_unrelated_product(): void
    {
        $user = $this->student();
        $this->cartCourse($user, ['price' => '25.00']);
        $inactive = Coupon::factory()->create(['code' => 'INACTIVE', 'is_active' => false]);
        $this->putJson('/api/v1/me/cart/coupon', ['code' => $inactive->code])->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
        Coupon::factory()->create(['code' => 'EXPIRED', 'expires_at' => now()->subSecond()]);
        $this->putJson('/api/v1/me/cart/coupon', ['code' => 'EXPIRED'])->assertUnprocessable();
        Coupon::factory()->create(['code' => 'TOOSOON', 'starts_at' => now()->addDay()]);
        $this->putJson('/api/v1/me/cart/coupon', ['code' => 'TOOSOON'])->assertUnprocessable();
        Coupon::factory()->create(['code' => 'MINIMUM', 'minimum_order_amount' => '26.00']);
        $this->putJson('/api/v1/me/cart/coupon', ['code' => 'MINIMUM'])->assertUnprocessable();
        $other = Course::factory()->published()->create();
        Coupon::factory()->create(['code' => 'OTHER', 'applies_to' => 'course', 'product_ids' => [$other->id]]);
        $this->putJson('/api/v1/me/cart/coupon', ['code' => 'OTHER'])->assertUnprocessable();
    }

    public function test_package_specific_coupon_and_per_user_limit_are_authoritative(): void
    {
        $user = $this->student();
        $package = Package::factory()->published()->create(['price' => '40.00']);
        $cart = Cart::factory()->for($user)->create();
        CartItem::factory()->forPackage($package)->for($cart)->create();
        Coupon::factory()->create(['code' => 'PACKAGE', 'discount_value' => '5.00', 'applies_to' => 'package', 'product_ids' => [$package->id], 'per_user_limit' => 1]);
        $this->putJson('/api/v1/me/cart/coupon', ['code' => 'package'])->assertJsonPath('data.estimated_total', '35.00');
        $this->postJson('/api/v1/me/checkout', $this->checkout('35.00', 'PACKAGE'))->assertCreated();
        CartItem::factory()->forPackage($package)->for($cart)->create();
        $this->putJson('/api/v1/me/cart/coupon', ['code' => 'PACKAGE'])->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
    }

    public function test_malformed_promotion_is_safely_inactive(): void
    {
        $course = Course::factory()->create(['price' => '100.00', 'promotional_price' => '120.00', 'discount_starts_at' => now()->subHour(), 'discount_ends_at' => now()->addHour()]);
        $this->assertSame('100.00', app(CommercePricingService::class)->product($course)['active_price']);
        $course->update(['promotional_price' => '80.00', 'discount_ends_at' => null]);
        $this->assertSame('100.00', app(CommercePricingService::class)->product($course->fresh())['active_price']);
    }

    public function test_manual_payment_approval_consumes_reserved_coupon_atomically(): void
    {
        $user = $this->student();
        $this->cartCourse($user, ['price' => '100.00']);
        Coupon::factory()->create(['code' => 'PAID', 'discount_value' => '10.00']);
        $this->putJson('/api/v1/me/cart/coupon', ['code' => 'PAID'])->assertOk();
        $orderId = $this->postJson('/api/v1/me/checkout', $this->checkout('90.00', 'PAID'))->assertCreated()->json('data.id');
        $payment = Payment::factory()->for(Order::findOrFail($orderId))->create(['amount' => '90.00']);
        app(PaymentReviewService::class)->approve($payment, User::factory()->create());
        $this->assertDatabaseHas('coupon_redemptions', ['order_id' => $orderId, 'status' => 'consumed']);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'completed']);
    }

    private function student(): User
    {
        config()->set('jcec.commerce.currency', 'JOD');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    /** @param array<string, mixed> $attributes */
    private function cartCourse(User $user, array $attributes): Course
    {
        $course = Course::factory()->published()->create($attributes);
        $cart = Cart::factory()->for($user)->create();
        CartItem::factory()->for($cart)->create(['purchasable_id' => $course->id]);

        return $course;
    }

    /** @return array<string, string|null> */
    private function checkout(string $total, ?string $code = null): array
    {
        return ['idempotency_key' => (string) Str::uuid(), 'expected_total' => $total, 'expected_coupon_code' => $code, 'customer_name' => 'Student', 'customer_email' => 'student@example.com', 'customer_phone' => '+970599000000'];
    }
}
