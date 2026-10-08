<?php

namespace Tests\Feature\Api\V1\Me;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\OrderStatus;
use App\PaymentMethod;
use App\PaymentStatus;
use App\Services\PaymentSubmissionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentSubmissionApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_submits_private_payment_proof_with_server_derived_amount_and_currency(): void
    {
        Storage::fake('local');
        $user = $this->authenticate();
        $order = Order::factory()->for($user)->create(['total' => '125.75', 'currency' => 'JOD']);

        $this->post("/api/v1/me/orders/{$order->id}/payments", [
            'method' => PaymentMethod::BankTransfer->value,
            'transaction_id' => 'BANK-123',
            'payment_proof' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
            'amount' => '0.01',
            'currency' => 'USD',
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.amount', '125.75')
            ->assertJsonPath('data.currency', 'JOD')
            ->assertJsonPath('data.status', PaymentStatus::PendingReview->value)
            ->assertJsonMissingPath('data.payment_proof');

        $payment = Payment::query()->sole();
        Storage::disk('local')->assertExists($payment->payment_proof);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);
    }

    public function test_returns_422_for_invalid_method_or_proof(): void
    {
        Storage::fake('local');
        $user = $this->authenticate();
        $order = Order::factory()->for($user)->create();

        $this->post("/api/v1/me/orders/{$order->id}/payments", [
            'method' => 'credit_card',
            'payment_proof' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()
            ->assertJsonValidationErrors(['method', 'payment_proof']);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_proof_uses_readable_temporary_path_when_realpath_is_unavailable(): void
    {
        Storage::fake('local');
        $user = $this->authenticate();
        $order = Order::factory()->for($user)->create();
        $temporaryFile = UploadedFile::fake()->create('proof.pdf', 1, 'application/pdf');
        $proof = new class($temporaryFile->getPathname()) extends UploadedFile
        {
            public function __construct(string $path)
            {
                parent::__construct($path, 'proof.pdf', 'application/pdf', null, true);
            }

            public function getRealPath(): string|false
            {
                return false;
            }
        };

        $payment = app(PaymentSubmissionService::class)->submit($order, $user, PaymentMethod::BankTransfer, $proof);

        Storage::disk('local')->assertExists($payment->payment_proof);
        $this->assertSame(PaymentStatus::PendingReview, $payment->status);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);
    }

    public function test_missing_temporary_payment_proof_is_rejected_without_creating_a_payment(): void
    {
        Storage::fake('local');
        $user = $this->authenticate();
        $order = Order::factory()->for($user)->create();
        Storage::disk('local')->put('vanished.pdf', 'temporary content');
        $proof = new UploadedFile(Storage::disk('local')->path('vanished.pdf'), 'proof.pdf', 'application/pdf', null, true);
        Storage::disk('local')->delete('vanished.pdf');

        try {
            app(PaymentSubmissionService::class)->submit($order, $user, PaymentMethod::BankTransfer, $proof);
            $this->fail('An unavailable payment proof must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment_proof', $exception->errors());
        }

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_student_cannot_submit_payment_for_another_students_order(): void
    {
        Storage::fake('local');
        $this->authenticate();
        $foreignOrder = Order::factory()->create();

        $this->post("/api/v1/me/orders/{$foreignOrder->id}/payments", $this->paymentPayload(), [
            'Accept' => 'application/json',
        ])->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cancelled_and_paid_orders_reject_payment_submission(): void
    {
        Storage::fake('local');
        $user = $this->authenticate();
        $cancelledOrder = Order::factory()->for($user)->create(['status' => OrderStatus::Cancelled]);
        $paidOrder = Order::factory()->paid()->for($user)->create();

        $this->post("/api/v1/me/orders/{$cancelledOrder->id}/payments", $this->paymentPayload(), [
            'Accept' => 'application/json',
        ])->assertUnprocessable()->assertJsonValidationErrors('order');

        $this->post("/api/v1/me/orders/{$paidOrder->id}/payments", $this->paymentPayload(), [
            'Accept' => 'application/json',
        ])->assertUnprocessable()->assertJsonValidationErrors('order');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_second_submission_is_blocked_while_payment_awaits_review_but_allowed_after_rejection(): void
    {
        Storage::fake('local');
        $user = $this->authenticate();
        $order = Order::factory()->for($user)->create();
        $pendingPayment = Payment::factory()->for($order)->create();
        $order->update(['status' => OrderStatus::AwaitingPayment]);

        $this->post("/api/v1/me/orders/{$order->id}/payments", $this->paymentPayload(), [
            'Accept' => 'application/json',
        ])->assertUnprocessable()->assertJsonValidationErrors('order');

        $pendingPayment->update([
            'status' => PaymentStatus::Rejected,
            'rejection_reason' => 'Unreadable proof.',
        ]);
        $order->update(['status' => OrderStatus::Pending]);

        $this->post("/api/v1/me/orders/{$order->id}/payments", $this->paymentPayload(), [
            'Accept' => 'application/json',
        ])->assertCreated();

        $this->assertDatabaseCount('payments', 2);
    }

    public function test_payment_proof_is_downloadable_only_by_order_owner(): void
    {
        Storage::fake('local');
        $owner = $this->authenticate();
        $order = Order::factory()->for($owner)->create();
        $payment = Payment::factory()->for($order)->create(['payment_proof' => 'payment-proofs/private.pdf']);
        Storage::disk('local')->put($payment->payment_proof, 'private-proof');

        $this->get("/api/v1/me/payments/{$payment->id}/proof", ['Accept' => 'application/json'])
            ->assertOk()
            ->assertHeader('content-disposition');

        $otherStudent = User::factory()->create();
        Sanctum::actingAs($otherStudent);
        $this->getJson("/api/v1/me/payments/{$payment->id}/proof")->assertNotFound();
    }

    /** @return array<string, mixed> */
    private function paymentPayload(): array
    {
        return [
            'method' => PaymentMethod::Manual->value,
            'payment_proof' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
        ];
    }

    private function authenticate(): User
    {
        config()->set('jcec.commerce.payment_proof_disk', 'local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }
}
