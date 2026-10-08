<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\PaymentReviewService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use LogicException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_audit_events_are_allowlisted_append_only_and_admin_visible(): void
    {
        $actor = User::factory()->create();
        $subject = User::factory()->create();
        $event = app(AuditTrail::class)->record('user.privileges_changed', $subject, $actor, [
            'changed_fields' => 'roles',
            'password' => 'secret-do-not-store',
            'body' => 'private-note-do-not-store',
        ]);

        $this->assertSame($actor->id, $event->actor_id);
        $this->assertSame($subject->id, $event->subject_id);
        $this->assertSame(['changed_fields' => 'roles'], $event->metadata);
        try {
            $event->update(['event_type' => 'tampered']);
            $this->fail('Audit events must not be mutable.');
        } catch (LogicException) {
            $this->assertSame('user.privileges_changed', $event->fresh()->event_type);
        }

        $this->actingAs($actor)->getJson('/api/v1/admin/audit-events')->assertForbidden();
        Role::findOrCreate('admin', 'web');
        $actor->assignRole('admin');
        $this->actingAs($actor)->getJson('/api/v1/admin/audit-events?event_type=user.privileges_changed')
            ->assertOk()->assertJsonPath('data.0.event_type', 'user.privileges_changed')
            ->assertJsonMissing(['password' => 'secret-do-not-store']);
        $this->actingAs($actor)->patchJson("/api/v1/admin/audit-events/{$event->id}", ['event_type' => 'tampered'])->assertNotFound();
    }

    public function test_payment_decision_records_actor_without_reason_content(): void
    {
        $actor = User::factory()->create();
        $order = Order::factory()->awaitingPayment()->create();
        $payment = Payment::factory()->for($order)->create(['status' => 'pending_review']);

        app(PaymentReviewService::class)->reject($payment, 'private rejection reason', $actor);

        $event = AuditEvent::query()->where('event_type', 'payment.rejected')->sole();
        $this->assertSame($actor->id, $event->actor_id);
        $this->assertStringNotContainsString('private rejection reason', json_encode($event->metadata));
    }
}
