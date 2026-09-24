<?php

namespace Tests\Feature\Policies;

use App\Models\Order;
use App\Models\User;
use App\PermissionName;
use App\Policies\OrderPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OrderPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_view_own_order_but_not_another_users_order(): void
    {
        $owner = User::factory()->create();
        $order = Order::factory()->for($owner)->create();
        $otherUser = User::factory()->create();
        $policy = new OrderPolicy;

        $this->assertTrue($policy->view($owner, $order));
        $this->assertFalse($policy->view($otherUser, $order));
    }

    public function test_order_permissions_control_administrative_abilities(): void
    {
        foreach ([PermissionName::OrdersView, PermissionName::OrdersManage] as $permission) {
            Permission::findOrCreate($permission->value);
        }
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(PermissionName::OrdersView->value);
        $manager = User::factory()->create();
        $manager->givePermissionTo(PermissionName::OrdersManage->value);
        $order = Order::factory()->create();
        $policy = new OrderPolicy;

        $this->assertTrue($policy->viewAny($viewer));
        $this->assertTrue($policy->view($viewer, $order));
        $this->assertFalse($policy->manage($viewer, $order));
        $this->assertTrue($policy->manage($manager, $order));
        $this->assertTrue($policy->update($manager, $order));
        $this->assertFalse($policy->delete($manager, $order));
        $this->assertFalse($policy->forceDelete($manager, $order));
    }

    public function test_authenticated_user_can_create_order(): void
    {
        $user = User::factory()->create();

        $this->assertTrue((new OrderPolicy)->create($user));
    }
}
