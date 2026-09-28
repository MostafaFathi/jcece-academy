<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackageCourse;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceCurrencyApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_catalog_prices_expose_the_normalized_configured_currency(): void
    {
        config()->set('jcec.commerce.currency', 'eur');
        $course = Course::factory()->published()->create(['price' => '19.25']);
        $package = Package::factory()->published()->create();
        PackageCourse::factory()->for($package)->for($course)->create();

        $this->getJson('/api/v1/courses')->assertOk()->assertJsonPath('data.0.currency', 'EUR');
        $this->getJson("/api/v1/courses/{$course->slug}")->assertOk()
            ->assertJsonPath('data.currency', 'EUR')->assertJsonPath('data.price', '19.25');
        $this->getJson('/api/v1/packages')->assertOk()->assertJsonPath('data.0.currency', 'EUR');
        $this->getJson("/api/v1/packages/{$package->slug}")->assertOk()
            ->assertJsonPath('data.currency', 'EUR')->assertJsonPath('data.courses.0.course.currency', 'EUR');
    }

    public function test_missing_or_invalid_currency_is_not_silently_defaulted(): void
    {
        $course = Course::factory()->published()->create();
        $package = Package::factory()->published()->create();
        Sanctum::actingAs(User::factory()->create());

        foreach ([null, '', 'INVALID', '12$'] as $currency) {
            config()->set('jcec.commerce.currency', $currency);
            $this->getJson("/api/v1/courses/{$course->slug}")->assertOk()->assertJsonPath('data.currency', null);
            $this->getJson("/api/v1/packages/{$package->slug}")->assertOk()->assertJsonPath('data.currency', null);
            $this->getJson('/api/v1/me/cart')->assertSuccessful()->assertJsonPath('data.currency', null);
        }
    }

    public function test_cart_mutations_expose_server_currency_and_product_access_duration(): void
    {
        config()->set('jcec.commerce.currency', 'JOD');
        Sanctum::actingAs(User::factory()->create());
        $course = Course::factory()->published()->create(['price' => '10.25', 'access_duration_days' => 90]);
        $package = Package::factory()->published()->create(['price' => '20.35', 'access_duration_days' => null]);
        $this->getJson('/api/v1/me/cart')->assertSuccessful()->assertJsonPath('data.currency', 'JOD');
        $this->postJson('/api/v1/me/cart/items', ['purchasable_type' => 'course', 'purchasable_id' => $course->id])
            ->assertOk()->assertJsonPath('data.currency', 'JOD')->assertJsonPath('data.items.0.product.access_duration_days', 90);
        $response = $this->postJson('/api/v1/me/cart/items', ['purchasable_type' => 'package', 'purchasable_id' => $package->id])
            ->assertOk()->assertJsonPath('data.currency', 'JOD')->assertJsonPath('data.estimated_total', '30.60')
            ->assertJsonPath('data.items.1.product.access_duration_days', null);
        $this->deleteJson('/api/v1/me/cart/items/'.$response->json('data.items.0.id'))
            ->assertOk()->assertJsonPath('data.currency', 'JOD');
        $this->deleteJson('/api/v1/me/cart')->assertOk()->assertJsonPath('data.currency', 'JOD');
    }

    public function test_order_keeps_snapshot_currency_and_exposes_the_configured_proof_limit(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        config()->set('jcec.commerce.currency', 'EUR');
        config()->set('jcec.commerce.payment_proof_max_kilobytes', 1024);
        $order = Order::factory()->for($user)->create(['currency' => 'JOD']);
        $this->getJson("/api/v1/me/orders/{$order->id}")->assertOk()
            ->assertJsonPath('data.currency', 'JOD')->assertJsonPath('data.payment_proof_max_kilobytes', 1024)
            ->assertJsonMissingPath('data.idempotency_key');
    }
}
