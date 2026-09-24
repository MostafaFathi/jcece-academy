<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\OrderStatus;
use App\PaymentStatus;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentReviewApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sales_support_lists_only_payments_awaiting_review_by_default(): void
    {
        $this->authenticateAs(RoleName::SalesSupport);
        $pending = Payment::factory()->create();
        Payment::factory()->rejected()->create();

        $this->getJson('/api/v1/admin/payments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending->id);
    }

    public function test_student_cannot_review_payments(): void
    {
        $this->authenticateAs(RoleName::Student);
        $payment = Payment::factory()->create();

        $this->postJson("/api/v1/admin/payments/{$payment->id}/approve")->assertForbidden();
    }

    public function test_approval_marks_payment_and_order_paid_without_provisioning_access(): void
    {
        $this->travelTo('2026-09-23 12:00:00');
        $reviewer = $this->authenticateAs(RoleName::SalesSupport);
        $order = Order::factory()->awaitingPayment()->create(['total' => '125.75', 'currency' => 'JOD']);
        $payment = Payment::factory()->for($order)->create(['amount' => '125.75', 'currency' => 'JOD']);

        $this->postJson("/api/v1/admin/payments/{$payment->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Paid->value)
            ->assertJsonPath('data.approver.id', $reviewer->id);

        $payment->refresh();
        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame($reviewer->id, $payment->approved_by);
        $this->assertSame('2026-09-23 12:00:00', $payment->paid_at?->format('Y-m-d H:i:s'));
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('2026-09-23 12:00:00', $order->paid_at?->format('Y-m-d H:i:s'));
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('enrollment_access_grants', 0);
    }

    public function test_repeated_approval_is_idempotent(): void
    {
        $this->travelTo('2026-09-23 12:00:00');
        $this->authenticateAs(RoleName::SalesSupport);
        $order = Order::factory()->awaitingPayment()->create();
        $payment = Payment::factory()->for($order)->create();

        $this->postJson("/api/v1/admin/payments/{$payment->id}/approve")->assertOk();
        $approvedAt = $payment->fresh()->approved_at?->toISOString();
        $this->travel(10)->minutes();
        $this->postJson("/api/v1/admin/payments/{$payment->id}/approve")->assertOk();

        $this->assertSame($approvedAt, $payment->fresh()->approved_at?->toISOString());
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_rejection_preserves_attempt_and_returns_order_to_pending(): void
    {
        $this->authenticateAs(RoleName::SalesSupport);
        $order = Order::factory()->awaitingPayment()->create();
        $payment = Payment::factory()->for($order)->create();

        $this->postJson("/api/v1/admin/payments/{$payment->id}/reject", [
            'rejection_reason' => 'The transfer receipt is unreadable.',
        ])->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Rejected->value)
            ->assertJsonPath('data.rejection_reason', 'The transfer receipt is unreadable.');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::Rejected->value,
            'rejection_reason' => 'The transfer receipt is unreadable.',
        ]);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_approval_rejects_invalid_order_state_or_mismatched_amount(): void
    {
        $this->authenticateAs(RoleName::SalesSupport);
        $cancelledOrder = Order::factory()->create(['status' => OrderStatus::Cancelled]);
        $cancelledPayment = Payment::factory()->for($cancelledOrder)->create();
        $mismatchedOrder = Order::factory()->awaitingPayment()->create(['total' => 200]);
        $mismatchedPayment = Payment::factory()->for($mismatchedOrder)->create(['amount' => 100]);

        $this->postJson("/api/v1/admin/payments/{$cancelledPayment->id}/approve")
            ->assertUnprocessable()->assertJsonValidationErrors('order');
        $this->postJson("/api/v1/admin/payments/{$mismatchedPayment->id}/approve")
            ->assertUnprocessable()->assertJsonValidationErrors('payment');

        $this->assertSame(PaymentStatus::PendingReview, $cancelledPayment->fresh()->status);
        $this->assertSame(PaymentStatus::PendingReview, $mismatchedPayment->fresh()->status);
    }

    public function test_second_successful_payment_for_same_order_is_rejected(): void
    {
        $this->authenticateAs(RoleName::SalesSupport);
        $order = Order::factory()->awaitingPayment()->create();
        Payment::factory()->paid()->for($order)->create();
        $pendingPayment = Payment::factory()->for($order)->create();

        $this->postJson("/api/v1/admin/payments/{$pendingPayment->id}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment');

        $this->assertSame(PaymentStatus::PendingReview, $pendingPayment->fresh()->status);
    }

    public function test_authorized_staff_downloads_private_payment_proof(): void
    {
        Storage::fake('local');
        $this->authenticateAs(RoleName::SalesSupport);
        $payment = Payment::factory()->create(['payment_proof' => 'payment-proofs/review.pdf']);
        Storage::disk('local')->put($payment->payment_proof, 'proof');

        $this->get("/api/v1/admin/payments/{$payment->id}/proof", ['Accept' => 'application/json'])
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    private function authenticateAs(RoleName $role): User
    {
        config()->set('jcec.commerce.payment_proof_disk', 'local');
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role->value);
        Sanctum::actingAs($user);

        return $user;
    }
}
