<?php

namespace Tests\Feature\Api\V1\Me;

use App\CourseStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Course;
use App\Models\Package;
use App\Models\PackageCourse;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_checkout_recalculates_prices_and_snapshots_order_items(): void
    {
        $user = $this->authenticate();
        $cart = Cart::factory()->for($user)->create();
        $course = Course::factory()->published()->create([
            'title' => 'Decimal Course',
            'price' => '10.25',
            'access_duration_days' => 90,
        ]);
        $package = Package::factory()->published()->create([
            'title' => 'Decimal Package',
            'price' => '20.35',
            'access_duration_days' => 365,
        ]);
        CartItem::factory()->for($cart)->create([
            'purchasable_type' => 'course',
            'purchasable_id' => $course->id,
        ]);
        CartItem::factory()->forPackage($package)->for($cart)->create();

        $this->postJson('/api/v1/me/checkout', [
            ...$this->checkoutPayload(),
            'subtotal' => '0.01',
            'total' => '0.01',
            'currency' => 'USD',
        ])->assertCreated()
            ->assertJsonPath('data.currency', 'JOD')
            ->assertJsonPath('data.subtotal', '30.60')
            ->assertJsonPath('data.total', '30.60')
            ->assertJsonPath('data.items.0.title', 'Decimal Course')
            ->assertJsonPath('data.items.1.access_duration_days', 365);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'subtotal' => 30.60,
            'total' => 30.60,
            'currency' => 'JOD',
        ]);
        $this->assertDatabaseHas('order_items', [
            'purchasable_type' => 'course',
            'purchasable_id' => $course->id,
            'unit_price' => 10.25,
            'title' => 'Decimal Course',
        ]);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_checkout_snapshots_package_membership_duration_and_titles_immutably(): void
    {
        $user = $this->authenticate();
        $package = Package::factory()->published()->create([
            'title' => 'Original Package',
            'price' => 150,
            'access_duration_days' => 180,
        ]);
        $firstCourse = Course::factory()->published()->create(['title' => 'First Course']);
        $secondCourse = Course::factory()->published()->create(['title' => 'Second Course']);
        PackageCourse::factory()->for($package)->for($firstCourse)->create(['sort_order' => 1]);
        PackageCourse::factory()->for($package)->for($secondCourse)->create(['sort_order' => 0]);
        $cart = Cart::factory()->for($user)->create();
        CartItem::factory()->forPackage($package)->for($cart)->create();

        $response = $this->postJson('/api/v1/me/checkout', $this->checkoutPayload())->assertCreated();
        $orderId = $response->json('data.id');

        $package->update(['title' => 'Changed Package', 'price' => 999, 'access_duration_days' => 30]);
        $package->courseMemberships()->where('course_id', $secondCourse->id)->delete();
        $thirdCourse = Course::factory()->published()->create(['title' => 'Third Course']);
        PackageCourse::factory()->for($package)->for($thirdCourse)->create(['sort_order' => 0]);
        $firstCourse->update(['title' => 'Changed First Course']);
        $firstCourse->delete();
        $package->delete();

        $this->getJson("/api/v1/me/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.title', 'Original Package')
            ->assertJsonPath('data.items.0.unit_price', '150.00')
            ->assertJsonPath('data.items.0.access_duration_days', 180)
            ->assertJsonPath('data.items.0.package_courses.0.course_title', 'Second Course')
            ->assertJsonPath('data.items.0.package_courses.1.course_title', 'First Course')
            ->assertJsonCount(2, 'data.items.0.package_courses');

        $this->assertDatabaseMissing('order_item_package_courses', ['course_id' => $thirdCourse->id]);
    }

    public function test_retry_with_same_idempotency_key_returns_original_order_without_duplicate(): void
    {
        $user = $this->authenticate();
        $course = Course::factory()->published()->create(['price' => 50]);
        $cart = Cart::factory()->for($user)->create();
        CartItem::factory()->for($cart)->create(['purchasable_id' => $course->id]);
        $payload = $this->checkoutPayload();

        $firstResponse = $this->postJson('/api/v1/me/checkout', $payload)->assertCreated();
        $secondResponse = $this->postJson('/api/v1/me/checkout', $payload)->assertOk();
        $cart->delete();
        $thirdResponse = $this->postJson('/api/v1/me/checkout', $payload)->assertOk();

        $this->assertSame($firstResponse->json('data.id'), $secondResponse->json('data.id'));
        $this->assertSame($firstResponse->json('data.id'), $thirdResponse->json('data.id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_empty_cart_returns_422_and_creates_no_order(): void
    {
        $user = $this->authenticate();
        Cart::factory()->for($user)->create();

        $this->postJson('/api/v1/me/checkout', $this->checkoutPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cart');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_unavailable_product_returns_422_and_preserves_cart(): void
    {
        $user = $this->authenticate();
        $course = Course::factory()->published()->create();
        $cart = Cart::factory()->for($user)->create();
        $item = CartItem::factory()->for($cart)->create(['purchasable_id' => $course->id]);
        $course->update(['status' => CourseStatus::Draft]);

        $this->postJson('/api/v1/me/checkout', $this->checkoutPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('purchasable_id');

        $this->assertModelExists($item);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_requires_explicitly_configured_currency_and_preserves_cart(): void
    {
        $user = $this->authenticate();
        config()->set('jcec.commerce.currency', null);
        $course = Course::factory()->published()->create();
        $cart = Cart::factory()->for($user)->create();
        $item = CartItem::factory()->for($cart)->create(['purchasable_id' => $course->id]);

        $this->postJson('/api/v1/me/checkout', $this->checkoutPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('currency');

        $this->assertModelExists($item);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_does_not_create_enrollment_or_access_grant(): void
    {
        $user = $this->authenticate();
        $course = Course::factory()->published()->create();
        $cart = Cart::factory()->for($user)->create();
        CartItem::factory()->for($cart)->create(['purchasable_id' => $course->id]);

        $this->postJson('/api/v1/me/checkout', $this->checkoutPayload())->assertCreated();

        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('enrollment_access_grants', 0);
    }

    /** @return array<string, string> */
    private function checkoutPayload(): array
    {
        return [
            'idempotency_key' => '123e4567-e89b-42d3-a456-426614174000',
            'customer_name' => 'Sample Student',
            'customer_email' => 'student@example.com',
            'customer_phone' => '+970599000000',
            'expected_total' => app(CartService::class)->get(auth()->user())->estimated_total,
            'notes' => 'Please review the payment manually.',
        ];
    }

    private function authenticate(): User
    {
        config()->set('jcec.commerce.currency', 'JOD');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }
}
