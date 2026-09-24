<?php

namespace Tests\Feature\Policies;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\PermissionName;
use App\Policies\PaymentPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PaymentPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_view_submit_and_download_proof_for_own_order_only(): void
    {
        $owner = User::factory()->create();
        $order = Order::factory()->for($owner)->create();
        $payment = Payment::factory()->for($order)->create();
        $otherUser = User::factory()->create();
        $policy = new PaymentPolicy;

        $this->assertTrue($policy->submit($owner, $order));
        $this->assertTrue($policy->view($owner, $payment));
        $this->assertTrue($policy->viewProof($owner, $payment));
        $this->assertFalse($policy->submit($otherUser, $order));
        $this->assertFalse($policy->view($otherUser, $payment));
    }

    public function test_payment_permissions_control_staff_abilities(): void
    {
        foreach ([PermissionName::PaymentsView, PermissionName::PaymentsManage] as $permission) {
            Permission::findOrCreate($permission->value);
        }
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(PermissionName::PaymentsView->value);
        $manager = User::factory()->create();
        $manager->givePermissionTo(PermissionName::PaymentsManage->value);
        $payment = Payment::factory()->create();
        $policy = new PaymentPolicy;

        $this->assertTrue($policy->viewAny($viewer));
        $this->assertTrue($policy->view($viewer, $payment));
        $this->assertTrue($policy->viewProof($viewer, $payment));
        $this->assertFalse($policy->review($viewer, $payment));
        $this->assertTrue($policy->review($manager, $payment));
        $this->assertFalse($policy->delete($manager, $payment));
        $this->assertFalse($policy->forceDelete($manager, $payment));
    }

    public function test_authenticated_user_can_create_payment_attempt(): void
    {
        $user = User::factory()->create();

        $this->assertTrue((new PaymentPolicy)->create($user));
    }
}
