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

class OrderAccessProvisioningApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sales_support_reconciles_paid_order_and_receives_result(): void
    {
        $this->authenticateAs(RoleName::SalesSupport);
        [$order] = $this->createPaidOrder();

        $this->postJson("/api/v1/admin/orders/{$order->id}/provision-access")
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Completed->value)
            ->assertJsonPath('provisioning.grants_created', 1)
            ->assertJsonPath('provisioning.grants_existing', 0);

        $this->assertDatabaseCount('enrollments', 1);
        $this->assertDatabaseCount('enrollment_access_grants', 1);
    }

    public function test_reconciliation_of_already_provisioned_order_is_safe(): void
    {
        $this->authenticateAs(RoleName::SalesSupport);
        [$order] = $this->createPaidOrder();
        $this->postJson("/api/v1/admin/orders/{$order->id}/provision-access")->assertOk();

        $this->postJson("/api/v1/admin/orders/{$order->id}/provision-access")
            ->assertOk()
            ->assertJsonPath('provisioning.grants_created', 0)
            ->assertJsonPath('provisioning.grants_existing', 1);

        $this->assertDatabaseCount('enrollment_access_grants', 1);
    }

    public function test_student_cannot_reconcile_own_or_another_users_order(): void
    {
        $student = $this->authenticateAs(RoleName::Student);
        [$ownOrder] = $this->createPaidOrder($student);
        [$otherOrder] = $this->createPaidOrder();

        $this->postJson("/api/v1/admin/orders/{$ownOrder->id}/provision-access")->assertForbidden();
        $this->postJson("/api/v1/admin/orders/{$otherOrder->id}/provision-access")->assertForbidden();

        $this->assertDatabaseCount('enrollment_access_grants', 0);
    }

    public function test_unpaid_order_reconciliation_returns_422_without_access(): void
    {
        $this->authenticateAs(RoleName::SalesSupport);
        $course = Course::factory()->create();
        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->create(['purchasable_id' => $course->id]);

        $this->postJson("/api/v1/admin/orders/{$order->id}/provision-access")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order');

        $this->assertDatabaseCount('enrollment_access_grants', 0);
    }

    /** @return array{Order, Course} */
    private function createPaidOrder(?User $user = null): array
    {
        $user ??= User::factory()->create();
        $course = Course::factory()->create();
        $order = Order::factory()->paid()->for($user)->create();
        OrderItem::factory()->for($order)->create(['purchasable_id' => $course->id]);

        return [$order, $course];
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
