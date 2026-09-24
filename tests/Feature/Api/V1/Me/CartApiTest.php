<?php

namespace Tests\Feature\Api\V1\Me;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Course;
use App\Models\Package;
use App\Models\User;
use App\PurchasableType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_when_cart_is_requested_without_authentication(): void
    {
        $this->getJson('/api/v1/me/cart')->assertUnauthorized();
    }

    public function test_adds_published_course_and_package_and_calculates_current_total(): void
    {
        $user = $this->authenticate();
        $course = Course::factory()->published()->create(['price' => '10.25']);
        $package = Package::factory()->published()->create(['price' => '20.35']);

        $this->postJson('/api/v1/me/cart/items', [
            'purchasable_type' => PurchasableType::Course->value,
            'purchasable_id' => $course->id,
        ])->assertOk();

        $this->postJson('/api/v1/me/cart/items', [
            'purchasable_type' => PurchasableType::Package->value,
            'purchasable_id' => $package->id,
        ])->assertOk()
            ->assertJsonPath('data.item_count', 2)
            ->assertJsonPath('data.estimated_total', '30.60');

        $this->assertDatabaseHas('carts', ['user_id' => $user->id]);
        $this->assertDatabaseCount('cart_items', 2);
    }

    public function test_adding_same_product_twice_keeps_one_cart_item(): void
    {
        $this->authenticate();
        $course = Course::factory()->published()->create();
        $payload = [
            'purchasable_type' => PurchasableType::Course->value,
            'purchasable_id' => $course->id,
        ];

        $this->postJson('/api/v1/me/cart/items', $payload)->assertOk();
        $this->postJson('/api/v1/me/cart/items', $payload)->assertOk();

        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_returns_422_when_product_is_not_currently_published(): void
    {
        $this->authenticate();
        $draftCourse = Course::factory()->create();
        $futurePackage = Package::factory()->published()->create(['published_at' => now()->addDay()]);

        $this->postJson('/api/v1/me/cart/items', [
            'purchasable_type' => 'course',
            'purchasable_id' => $draftCourse->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('purchasable_id');

        $this->postJson('/api/v1/me/cart/items', [
            'purchasable_type' => 'package',
            'purchasable_id' => $futurePackage->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('purchasable_id');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_returns_422_for_arbitrary_morph_type(): void
    {
        $this->authenticate();

        $this->postJson('/api/v1/me/cart/items', [
            'purchasable_type' => User::class,
            'purchasable_id' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('purchasable_type');
    }

    public function test_user_cannot_remove_item_from_another_users_cart(): void
    {
        $this->authenticate();
        $foreignItem = CartItem::factory()->create();

        $this->deleteJson("/api/v1/me/cart/items/{$foreignItem->id}")->assertNotFound();
        $this->assertModelExists($foreignItem);
    }

    public function test_removes_item_and_clears_only_authenticated_users_cart(): void
    {
        $user = $this->authenticate();
        $cart = Cart::factory()->for($user)->create();
        $first = CartItem::factory()->for($cart)->create();
        CartItem::factory()->for($cart)->create();
        $foreignItem = CartItem::factory()->create();

        $this->deleteJson("/api/v1/me/cart/items/{$first->id}")
            ->assertOk()
            ->assertJsonPath('data.item_count', 1);

        $this->deleteJson('/api/v1/me/cart')
            ->assertOk()
            ->assertJsonPath('data.item_count', 0);

        $this->assertDatabaseMissing('cart_items', ['cart_id' => $cart->id]);
        $this->assertModelExists($foreignItem);
    }

    private function authenticate(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }
}
