<?php

namespace Tests\Feature\Api\V1\Me;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemPackageCourse;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lists_only_authenticated_users_orders(): void
    {
        $user = $this->authenticate();
        $ownOrder = Order::factory()->for($user)->create();
        Order::factory()->create();

        $this->getJson('/api/v1/me/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownOrder->id);
    }

    public function test_returns_404_for_another_users_order(): void
    {
        $this->authenticate();
        $foreignOrder = Order::factory()->create();

        $this->getJson("/api/v1/me/orders/{$foreignOrder->id}")->assertNotFound();
    }

    public function test_order_details_use_snapshots_and_hide_private_payment_fields(): void
    {
        $user = $this->authenticate();
        $order = Order::factory()->for($user)->create();
        $item = OrderItem::factory()->for($order)->forPackage()->create([
            'title' => 'Historical Package',
            'purchasable_id' => 999999,
        ]);
        OrderItemPackageCourse::factory()->for($item)->create([
            'course_id' => null,
            'course_title' => 'Historical Course',
        ]);
        Payment::factory()->for($order)->create([
            'payment_proof' => 'payment-proofs/private.pdf',
            'gateway_response' => ['secret' => 'internal'],
        ]);

        $this->getJson("/api/v1/me/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.title', 'Historical Package')
            ->assertJsonPath('data.items.0.package_courses.0.course_title', 'Historical Course')
            ->assertJsonPath('data.payments.0.proof_available', true)
            ->assertJsonMissingPath('data.payments.0.payment_proof')
            ->assertJsonMissingPath('data.payments.0.gateway_response')
            ->assertDontSee('private.pdf')
            ->assertDontSee('internal');
    }

    private function authenticate(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }
}
