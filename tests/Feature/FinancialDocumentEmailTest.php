<?php

namespace Tests\Feature;

use App\Jobs\SendTransactionalDelivery;
use App\Mail\TransactionalEventMail;
use App\Models\Course;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TransactionalDelivery;
use App\Models\User;
use App\OrderStatus;
use App\RoleName;
use App\Services\FinancialDocumentService;
use App\Services\TransactionalDeliveryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class FinancialDocumentEmailTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_pending_order_and_pending_refund_cannot_issue_final_receipts(): void
    {
        $order = Order::factory()->create();
        $student = $order->user;
        Sanctum::actingAs($student);
        $this->postJson("/api/v1/me/orders/{$order->id}/financial-documents")->assertUnprocessable();
        $this->assertDatabaseCount('financial_documents', 0);

        $completed = $this->completedOrder($student);
        $refund = $completed->refunds()->create(['amount' => '25.00', 'currency' => 'JOD', 'reason' => 'customer_request', 'status' => 'pending', 'access_effect' => 'none', 'initiated_by' => $student->id]);
        try {
            app(FinancialDocumentService::class)->issueRefund($refund);
            $this->fail('A pending refund cannot issue a receipt.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('financial_documents', 0);
        }
        $refund->update(['status' => 'rejected', 'processed_at' => now()]);
        try {
            app(FinancialDocumentService::class)->issueRefund($refund);
            $this->fail('A rejected refund cannot issue a receipt.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('financial_documents', 0);
        }
    }

    public function test_receipt_is_private_idempotent_and_uses_historical_snapshots(): void
    {
        Storage::fake('local');
        $student = User::factory()->create();
        $order = $this->completedOrder($student);
        $course = Course::query()->findOrFail($order->items()->first()->purchasable_id);
        $document = app(FinancialDocumentService::class)->issuePurchase($order);
        $again = app(FinancialDocumentService::class)->issuePurchase($order);
        $this->assertSame($document->id, $again->id);
        $this->assertSame('local', $document->pdf_disk);
        Storage::disk('local')->assertExists($document->pdf_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($document->pdf_path));
        $this->assertSame('Original course', $document->snapshot['items'][0]['title']);
        $this->assertSame('100.00', $document->snapshot['total']);
        $this->assertSame('Snapshot customer', $document->customer_name);
        $course->update(['title' => 'Changed course', 'price' => '500.00']);
        $student->update(['name' => 'Changed customer']);
        $this->assertSame('Original course', $document->fresh()->snapshot['items'][0]['title']);
        $this->assertSame('Snapshot customer', $document->fresh()->customer_name);
        $this->assertDatabaseCount('financial_documents', 1);

        Sanctum::actingAs($student);
        $this->getJson("/api/v1/me/orders/{$order->id}")->assertOk()->assertJsonPath('data.financial_documents.0.document_number', $document->document_number)->assertDontSee($document->pdf_path);
        $this->get("/api/v1/me/financial-documents/{$document->id}/download")->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_issued_document_rejects_ordinary_updates_and_deletes(): void
    {
        Storage::fake('local');
        $document = app(FinancialDocumentService::class)->issuePurchase($this->completedOrder(User::factory()->create()));

        try {
            $document->update(['amount' => '1.00']);
            $this->fail('Issued document update must be rejected.');
        } catch (LogicException) {
            $this->assertSame('100.00', $document->fresh()->amount);
        }

        try {
            $document->fresh()->delete();
            $this->fail('Issued document deletion must be rejected.');
        } catch (LogicException) {
            $this->assertDatabaseHas('financial_documents', ['id' => $document->id]);
        }
    }

    public function test_document_download_enforces_owner_and_explicit_admin_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        $order = $this->completedOrder(User::factory()->create());
        $document = app(FinancialDocumentService::class)->issuePurchase($order);
        $this->getJson("/api/v1/me/financial-documents/{$document->id}/download")->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/me/financial-documents/{$document->id}/download")->assertNotFound();
        $support = User::factory()->create();
        $support->assignRole(RoleName::SalesSupport->value);
        Sanctum::actingAs($support);
        $this->getJson("/api/v1/admin/financial-documents/{$document->id}/download")->assertForbidden();
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($admin);
        $this->get("/api/v1/admin/financial-documents/{$document->id}/download")->assertOk();
    }

    public function test_completed_refund_gets_a_linked_receipt_without_changing_purchase_receipt(): void
    {
        Storage::fake('local');
        $order = $this->completedOrder(User::factory()->create());
        $purchase = app(FinancialDocumentService::class)->issuePurchase($order);
        $refund = $order->refunds()->create(['amount' => '25.00', 'currency' => 'JOD', 'reason' => 'customer_request', 'status' => 'completed', 'access_effect' => 'none', 'initiated_by' => $order->user_id, 'processed_at' => now()]);
        $receipt = app(FinancialDocumentService::class)->issueRefund($refund);
        $this->assertSame('refund', $receipt->kind);
        $this->assertSame($purchase->document_number, $receipt->snapshot['purchase_document_number']);
        $this->assertSame($receipt->id, app(FinancialDocumentService::class)->issueRefund($refund)->id);
        $this->assertSame($purchase->pdf_sha256, $purchase->fresh()->pdf_sha256);
        $this->assertDatabaseCount('financial_documents', 2);
    }

    public function test_zero_total_completed_order_can_issue_arabic_receipt_without_fabricated_payment(): void
    {
        Storage::fake('local');
        $order = $this->completedOrder(User::factory()->create());
        $order->update(['subtotal' => '0.00', 'discount_total' => '0.00', 'total' => '0.00', 'locale_snapshot' => 'ar']);
        $order->items()->firstOrFail()->update(['unit_price' => '0.00', 'discount_amount' => '0.00', 'total' => '0.00']);
        $document = app(FinancialDocumentService::class)->issuePurchase($order);

        $this->assertSame('ar', $document->locale);
        $this->assertSame('complimentary', $document->snapshot['payment_method']);
        $this->assertSame('0.00', $document->amount);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($document->pdf_path));
    }

    public function test_delivery_snapshots_recipient_locale_and_sends_only_once(): void
    {
        Mail::fake();
        $user = User::factory()->create(['preferred_locale' => 'en']);
        $order = $this->completedOrder($user);
        $order->update(['customer_email' => 'purchase@example.test', 'locale_snapshot' => 'ar']);
        $service = app(TransactionalDeliveryService::class);
        $first = $service->recordForOrder('purchase_completed', 'Order', $order->id, $order);
        $second = $service->recordForOrder('purchase_completed', 'Order', $order->id, $order);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('ar', $first->locale);
        $this->assertSame('purchase@example.test', $first->recipient_email);
        $order->update(['customer_email' => 'new@example.test', 'locale_snapshot' => 'en']);
        (new SendTransactionalDelivery($first->id))->handle();
        (new SendTransactionalDelivery($first->id))->handle();
        Mail::assertSent(TransactionalEventMail::class, 1);
        $this->assertSame('sent', $first->fresh()->status);
        $this->assertSame(1, $first->fresh()->attempts);
        $this->assertSame('purchase@example.test', $first->fresh()->recipient_email);
        $this->assertDatabaseCount('transactional_deliveries', 1);
    }

    public function test_email_failure_does_not_change_completed_order_and_retry_requires_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $order = $this->completedOrder(User::factory()->create());
        $delivery = TransactionalDelivery::factory()->create(['order_id' => $order->id, 'user_id' => $order->user_id, 'status' => 'failed']);
        $support = User::factory()->create();
        $support->assignRole(RoleName::SalesSupport->value);
        Sanctum::actingAs($support);
        $this->postJson("/api/v1/admin/orders/{$order->id}/transactional-deliveries/{$delivery->id}/retry")->assertForbidden();
        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
    }

    public function test_admin_retry_reuses_failed_logical_delivery_and_is_audited(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $order = $this->completedOrder(User::factory()->create());
        $delivery = TransactionalDelivery::factory()->create(['order_id' => $order->id, 'user_id' => $order->user_id, 'status' => 'failed', 'event_key' => 'purchase_completed:Order:'.$order->id]);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/orders/{$order->id}/transactional-deliveries/{$delivery->id}/retry")
            ->assertOk()->assertJsonPath('data.id', $delivery->id)->assertJsonPath('data.status', 'queued');
        $this->assertDatabaseCount('transactional_deliveries', 1);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'transactional_delivery.retry_requested', 'subject_id' => $delivery->id]);
    }

    public function test_stale_queued_recovery_dispatches_only_old_rows(): void
    {
        Queue::fake();
        $old = TransactionalDelivery::factory()->create(['status' => 'queued', 'queued_at' => now()->subMinutes(11)]);
        TransactionalDelivery::factory()->create(['status' => 'queued', 'queued_at' => now()]);
        TransactionalDelivery::factory()->create(['status' => 'sending', 'queued_at' => now()->subMinutes(11)]);

        $this->artisan('transactional-deliveries:dispatch-stale')->assertSuccessful();

        Queue::assertPushed(SendTransactionalDelivery::class, fn (SendTransactionalDelivery $job): bool => $job->deliveryId === $old->id);
        Queue::assertCount(1);
        $this->assertTrue($old->fresh()->queued_at->greaterThan(now()->subMinute()));
    }

    private function completedOrder(User $user): Order
    {
        $order = Order::factory()->for($user)->create([
            'status' => OrderStatus::Completed, 'subtotal' => '120.00', 'discount_total' => '20.00',
            'total' => '100.00', 'customer_name' => 'Snapshot customer', 'customer_email' => 'snapshot@example.test',
            'locale_snapshot' => 'en', 'paid_at' => now(),
        ]);
        $course = Course::factory()->create(['title' => 'Original course', 'price' => '120.00']);
        OrderItem::factory()->for($order)->create(['purchasable_id' => $course->id, 'title' => 'Original course', 'unit_price' => '120.00', 'discount_amount' => '20.00', 'total' => '100.00']);

        return $order->refresh();
    }
}
