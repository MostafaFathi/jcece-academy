<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use App\RoleName;
use App\SupportTicketStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportTicketAdminApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sales_support_filters_views_and_posts_public_and_internal_messages(): void
    {
        $staff = $this->staff(RoleName::SalesSupport);
        $ticket = SupportTicket::factory()->create(['priority' => 'high']);
        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/admin/support-tickets?priority=high')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/admin/support-tickets/{$ticket->id}/messages", ['body' => 'Internal context', 'is_internal' => true])->assertCreated()->assertJsonPath('data.is_internal', true);
        $this->assertSame(SupportTicketStatus::Open, $ticket->fresh()->status);
        $this->postJson("/api/v1/admin/support-tickets/{$ticket->id}/messages", ['body' => 'We need more information'])->assertCreated()->assertJsonPath('data.is_internal', false);
        $this->assertSame(SupportTicketStatus::WaitingForStudent, $ticket->fresh()->status);
        $this->getJson("/api/v1/admin/support-tickets/{$ticket->id}/messages")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_assignment_requires_eligible_staff_and_records_actor_and_values(): void
    {
        $admin = $this->staff(RoleName::Admin);
        $support = $this->staff(RoleName::SalesSupport);
        $student = User::factory()->create();
        $ticket = SupportTicket::factory()->create();
        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/admin/support-tickets/{$ticket->id}/assignment", ['assigned_to' => $student->id])->assertUnprocessable()->assertJsonValidationErrors('assigned_to');
        $this->putJson("/api/v1/admin/support-tickets/{$ticket->id}/assignment", ['assigned_to' => $support->id])->assertOk()->assertJsonPath('data.assignee.id', $support->id);
        $this->assertDatabaseHas('support_ticket_activities', ['support_ticket_id' => $ticket->id, 'actor_id' => $admin->id, 'event_type' => 'assigned', 'previous_value' => null, 'new_value' => (string) $support->id]);
    }

    public function test_valid_status_changes_manage_timestamps_and_audit_history(): void
    {
        $admin = $this->staff(RoleName::Admin);
        $ticket = SupportTicket::factory()->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/support-tickets/{$ticket->id}", ['status' => 'resolved', 'priority' => 'urgent'])->assertOk()->assertJsonPath('data.status', 'resolved');
        $this->assertNotNull($ticket->fresh()->resolved_at);
        $this->patchJson("/api/v1/admin/support-tickets/{$ticket->id}", ['status' => 'closed'])->assertOk();
        $this->assertNotNull($ticket->fresh()->closed_at);
        $this->patchJson("/api/v1/admin/support-tickets/{$ticket->id}", ['status' => 'open'])->assertOk();
        $ticket->refresh();
        $this->assertNull($ticket->resolved_at);
        $this->assertNull($ticket->closed_at);
        $this->assertDatabaseHas('support_ticket_activities', ['support_ticket_id' => $ticket->id, 'actor_id' => $admin->id, 'event_type' => 'reopened', 'previous_value' => 'closed', 'new_value' => 'open']);
    }

    public function test_invalid_transition_and_stale_administrative_update_are_rejected(): void
    {
        $admin = $this->staff(RoleName::Admin);
        $ticket = SupportTicket::factory()->closed()->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/support-tickets/{$ticket->id}", ['status' => 'resolved'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->patchJson("/api/v1/admin/support-tickets/{$ticket->id}", ['priority' => 'high', 'expected_updated_at' => now()->subDay()->toJSON()])->assertUnprocessable()->assertJsonValidationErrors('expected_updated_at');
        $this->assertSame('normal', $ticket->fresh()->priority->value);
    }

    public function test_instructor_and_content_manager_have_no_global_support_access(): void
    {
        $ticket = SupportTicket::factory()->create();
        foreach ([RoleName::Instructor, RoleName::ContentManager] as $role) {
            Sanctum::actingAs($this->staff($role));
            $this->getJson('/api/v1/admin/support-tickets')->assertForbidden();
            $this->getJson("/api/v1/admin/support-tickets/{$ticket->id}")->assertForbidden();
        }
    }

    public function test_admin_detail_exposes_activity_but_student_detail_does_not(): void
    {
        $admin = $this->staff(RoleName::Admin);
        $ticket = SupportTicket::factory()->create();
        $ticket->activities()->create(['actor_id' => $admin->id, 'actor_name_snapshot' => $admin->name, 'event_type' => 'priority_changed', 'field_name' => 'priority', 'previous_value' => 'normal', 'new_value' => 'high']);
        Sanctum::actingAs($admin);
        $this->getJson("/api/v1/admin/support-tickets/{$ticket->id}")->assertOk()->assertJsonCount(1, 'data.activities');
        Sanctum::actingAs($ticket->user);
        $this->getJson("/api/v1/me/support-tickets/{$ticket->id}")->assertOk()->assertJsonMissingPath('data.activities');
    }

    public function test_ticket_owner_cannot_use_admin_routes_or_create_internal_notes(): void
    {
        $ticket = SupportTicket::factory()->create();
        Sanctum::actingAs($ticket->user);

        $this->getJson("/api/v1/admin/support-tickets/{$ticket->id}")->assertForbidden();
        $this->getJson("/api/v1/admin/support-tickets/{$ticket->id}/messages")->assertForbidden();
        $this->postJson("/api/v1/admin/support-tickets/{$ticket->id}/messages", ['body' => 'Fake note', 'is_internal' => true])->assertForbidden();
        $this->assertDatabaseCount('support_ticket_messages', 0);
    }

    private function staff(RoleName $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }
}
