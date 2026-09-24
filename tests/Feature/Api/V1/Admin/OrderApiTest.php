<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Course;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\OrderStatus;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sales_support_lists_and_inspects_orders(): void
    {
        $this->authenticateAs(RoleName::SalesSupport);
        $order = Order::factory()->create(['order_number' => 'JCEC-SEARCH-001']);
        OrderItem::factory()->for($order)->create(['title' => 'Snapshot Product']);

        $this->getJson('/api/v1/admin/orders?search=SEARCH')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $order->id);

        $this->getJson("/api/v1/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.title', 'Snapshot Product');
    }

    public function test_student_cannot_use_admin_order_api(): void
    {
        $this->authenticateAs(RoleName::Student);

        $this->getJson('/api/v1/admin/orders')->assertForbidden();
    }

    public function test_sales_support_cancels_pending_order(): void
    {
        $this->authenticateAs(RoleName::SalesSupport);
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => OrderStatus::Cancelled->value,
        ])->assertOk()->assertJsonPath('data.status', OrderStatus::Cancelled->value);

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_sales_support_completes_paid_order(): void
    {
        $this->authenticateAs(RoleName::SalesSupport);
        $order = Order::factory()->paid()->create();
        $course = Course::factory()->create();
        OrderItem::factory()->for($order)->create(['purchasable_id' => $course->id]);

        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => OrderStatus::Completed->value,
        ])->assertOk()->assertJsonPath('data.status', OrderStatus::Completed->value);

        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
        $this->assertDatabaseHas('enrollments', ['user_id' => $order->user_id, 'course_id' => $course->id]);
    }

    public function test_invalid_status_transition_returns_422_without_changing_order(): void
    {
        $this->authenticateAs(RoleName::SalesSupport);
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => OrderStatus::Completed->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_refunded_status_is_reserved_and_cannot_be_set(): void
    {
        $this->authenticateAs(RoleName::SalesSupport);
        $order = Order::factory()->paid()->create();

        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => OrderStatus::Refunded->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    private function authenticateAs(RoleName $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role->value);
        Sanctum::actingAs($user);

        return $user;
    }
}
