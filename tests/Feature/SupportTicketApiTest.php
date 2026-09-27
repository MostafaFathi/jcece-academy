<?php

namespace Tests\Feature;

use App\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\SupportTicketStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportTicketApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_student_creates_ticket_with_owned_references_and_server_fields(): void
    {
        $student = User::factory()->create();
        $order = Order::factory()->for($student)->create();
        $course = Course::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($course)->create(['status' => EnrollmentStatus::Active]);
        EnrollmentAccessGrant::factory()->for($enrollment)->lifetime()->create();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/v1/me/support-tickets', [
            'subject' => 'Payment is not reflected', 'category' => 'payment', 'body' => 'Please review my payment.',
            'related_order_id' => $order->id, 'related_course_id' => $course->id,
            'user_id' => User::factory()->create()->id, 'assigned_to' => User::factory()->create()->id,
            'priority' => 'urgent', 'status' => 'closed', 'ticket_number' => 'CLIENT-VALUE',
        ])->assertCreated()->assertJsonPath('data.status', 'open')->assertJsonPath('data.priority', 'normal');

        $id = $response->json('data.id');
        $this->assertDatabaseHas('support_tickets', ['id' => $id, 'user_id' => $student->id, 'assigned_to' => null, 'related_order_id' => $order->id, 'related_course_id' => $course->id]);
        $this->assertDatabaseHas('support_ticket_messages', ['support_ticket_id' => $id, 'user_id' => $student->id, 'is_internal' => false]);
        $this->assertDatabaseHas('support_ticket_activities', ['support_ticket_id' => $id, 'actor_id' => $student->id, 'event_type' => 'created']);
        $this->assertNotSame('CLIENT-VALUE', SupportTicket::query()->findOrFail($id)->ticket_number);
    }

    public function test_ticket_numbers_are_unique(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);
        $payload = ['subject' => 'Help needed', 'category' => 'general', 'body' => 'Please help.'];
        $this->postJson('/api/v1/me/support-tickets', $payload)->assertCreated();
        $this->postJson('/api/v1/me/support-tickets', $payload)->assertCreated();
        $this->assertSame(2, SupportTicket::query()->distinct()->count('ticket_number'));
    }

    public function test_order_and_course_references_must_belong_to_student_without_disclosing_records(): void
    {
        $student = User::factory()->create();
        $other = User::factory()->create();
        $order = Order::factory()->for($other)->create();
        $course = Course::factory()->create();
        Sanctum::actingAs($student);
        $payload = ['subject' => 'Question', 'category' => 'general', 'body' => 'Details'];

        $this->postJson('/api/v1/me/support-tickets', $payload + ['related_order_id' => $order->id])->assertUnprocessable()->assertJsonValidationErrors('related_order_id');
        $this->postJson('/api/v1/me/support-tickets', $payload + ['related_course_id' => $course->id])->assertUnprocessable()->assertJsonValidationErrors('related_course_id');
        $this->assertDatabaseCount('support_tickets', 0);
    }

    public function test_category_is_validated_and_student_cannot_set_admin_fields(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);
        $this->postJson('/api/v1/me/support-tickets', ['subject' => 'Question', 'category' => 'invalid', 'body' => 'Details'])->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->assertDatabaseCount('support_tickets', 0);
    }

    public function test_student_lists_views_and_replies_only_to_owned_tickets(): void
    {
        $student = User::factory()->create();
        $owned = SupportTicket::factory()->for($student)->create();
        $foreign = SupportTicket::factory()->create();
        Sanctum::actingAs($student);

        $this->getJson('/api/v1/me/support-tickets')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $owned->id);
        $this->getJson("/api/v1/me/support-tickets/{$foreign->id}")->assertNotFound();
        $this->postJson("/api/v1/me/support-tickets/{$foreign->id}/messages", ['body' => 'Intrusion'])->assertNotFound();
        $this->postJson("/api/v1/me/support-tickets/{$owned->id}/messages", ['body' => 'Student reply'])->assertCreated();
    }

    public function test_internal_notes_never_appear_in_student_message_data_or_counts(): void
    {
        $student = User::factory()->create();
        $ticket = SupportTicket::factory()->for($student)->create();
        SupportTicketMessage::factory()->for($ticket, 'ticket')->for($student)->create(['body' => 'Visible']);
        SupportTicketMessage::factory()->internal()->for($ticket, 'ticket')->create(['body' => 'Secret note']);
        Sanctum::actingAs($student);

        $response = $this->getJson("/api/v1/me/support-tickets/{$ticket->id}/messages")->assertOk()->assertJsonCount(1, 'data');
        $this->assertStringNotContainsString('Secret note', $response->getContent());
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_reply_workflow_and_closed_ticket_requires_explicit_reopen(): void
    {
        $student = User::factory()->create();
        $waiting = SupportTicket::factory()->for($student)->create(['status' => SupportTicketStatus::WaitingForStudent]);
        $resolved = SupportTicket::factory()->resolved()->for($student)->create();
        $closed = SupportTicket::factory()->closed()->for($student)->create();
        Sanctum::actingAs($student);

        $this->postJson("/api/v1/me/support-tickets/{$waiting->id}/messages", ['body' => 'Here is more detail'])->assertCreated();
        $this->assertSame(SupportTicketStatus::InProgress, $waiting->fresh()->status);
        $this->postJson("/api/v1/me/support-tickets/{$resolved->id}/messages", ['body' => 'Issue returned'])->assertCreated();
        $this->assertSame(SupportTicketStatus::Open, $resolved->fresh()->status);
        $this->postJson("/api/v1/me/support-tickets/{$closed->id}/messages", ['body' => 'Issue returned'])->assertUnprocessable();
        $this->postJson("/api/v1/me/support-tickets/{$closed->id}/reopen")->assertOk()->assertJsonPath('data.status', 'open');
        $this->postJson("/api/v1/me/support-tickets/{$closed->id}/messages", ['body' => 'Now reopened'])->assertCreated();
    }
}
