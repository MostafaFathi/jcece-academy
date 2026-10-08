<?php

namespace Tests\Feature;

use App\Mail\TransactionalEventMail;
use App\Models\Course;
use App\Models\FinancialDocument;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\TransactionalDelivery;
use App\Models\User;
use App\OrderStatus;
use App\Services\OrderAccessProvisioningService;
use App\Services\PaymentReviewService;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class TransactionalCommitTest extends TestCase
{
    use RefreshDatabase;

    public function refreshDatabase(): void
    {
        $this->artisan('migrate:fresh', ['--no-interaction' => true]);
    }

    /** @return array<int, string> */
    protected function connectionsToTransact(): array
    {
        return [];
    }

    public function test_approval_emits_only_after_commit_and_retry_keeps_one_document_and_one_message(): void
    {
        Storage::fake('local');
        Mail::fake();
        $student = User::factory()->create();
        $approver = User::factory()->create();
        $order = Order::factory()->awaitingPayment()->for($student)->create(['locale_snapshot' => 'en']);
        $course = Course::factory()->create();
        OrderItem::factory()->for($order)->create(['purchasable_id' => $course->id]);
        $payment = Payment::factory()->for($order)->create();

        DB::transaction(function () use ($payment, $approver, $order): void {
            app(PaymentReviewService::class)->approve($payment, $approver);
            $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
            $this->assertSame(0, FinancialDocument::query()->count());
            $this->assertSame('queued', TransactionalDelivery::query()->where('message_type', 'purchase_completed')->firstOrFail()->status);
            Mail::assertNothingSent();
        });

        $this->assertDatabaseCount('financial_documents', 1);
        $this->assertDatabaseCount('transactional_deliveries', 1);
        $this->assertSame('sent', TransactionalDelivery::query()->firstOrFail()->status);
        Mail::assertSent(TransactionalEventMail::class, 1);
        app(PaymentReviewService::class)->approve($payment, $approver);
        $this->assertDatabaseCount('financial_documents', 1);
        $this->assertDatabaseCount('transactional_deliveries', 1);

        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
    }

    public function test_mail_transport_failure_does_not_roll_back_approved_payment_or_access(): void
    {
        Storage::fake('local');
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Mail transport unavailable'));
        $student = User::factory()->create();
        $order = Order::factory()->awaitingPayment()->for($student)->create();
        $course = Course::factory()->create();
        OrderItem::factory()->for($order)->create(['purchasable_id' => $course->id]);
        $payment = Payment::factory()->for($order)->create();

        app(PaymentReviewService::class)->approve($payment, User::factory()->create());

        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
        $this->assertDatabaseHas('financial_documents', ['order_id' => $order->id, 'kind' => 'purchase']);
        $this->assertDatabaseHas('transactional_deliveries', ['order_id' => $order->id, 'status' => 'failed', 'attempts' => 1]);
        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => $course->id]);
        $this->assertDatabaseCount('enrollment_access_grants', 1);
    }

    public function test_refund_receipt_and_message_follow_completed_refund_commit(): void
    {
        Storage::fake('local');
        Mail::fake();
        $student = User::factory()->create();
        $operator = User::factory()->create();
        $order = Order::factory()->paid()->for($student)->create();
        $course = Course::factory()->create();
        OrderItem::factory()->for($order)->create(['purchasable_id' => $course->id]);
        Payment::factory()->paid()->for($order)->create();
        app(OrderAccessProvisioningService::class)->provision($order);
        $refunds = app(RefundService::class);
        $refund = $refunds->initiate($order, $operator, ['amount' => '20.00', 'reason' => 'customer_request', 'access_effect' => 'none']);

        DB::transaction(function () use ($refunds, $refund, $operator): void {
            $refunds->complete($refund, $operator);
            $this->assertDatabaseMissing('financial_documents', ['refund_id' => $refund->id]);
            $this->assertDatabaseHas('transactional_deliveries', ['message_type' => 'refund_completed', 'status' => 'queued']);
        });

        $this->assertDatabaseHas('financial_documents', ['refund_id' => $refund->id, 'kind' => 'refund']);
        $this->assertDatabaseHas('transactional_deliveries', ['message_type' => 'refund_completed', 'status' => 'sent']);
        $this->assertDatabaseCount('financial_documents', 2);
        Mail::assertSent(TransactionalEventMail::class, 2);
    }
}
