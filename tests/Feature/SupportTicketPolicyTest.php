<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use App\Policies\SupportTicketPolicy;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SupportTicketPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_student_can_view_and_reply_to_own_ticket_only(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ticket = SupportTicket::factory()->for($owner)->create();
        $policy = app(SupportTicketPolicy::class);
        $this->assertTrue($policy->view($owner, $ticket));
        $this->assertTrue($policy->reply($owner, $ticket));
        $this->assertFalse($policy->view($other, $ticket));
        $this->assertFalse($policy->reply($other, $ticket));
        $this->assertFalse($policy->manage($owner, $ticket));
        $this->assertFalse($policy->replyAsStaff($owner, $ticket));
    }

    #[DataProvider('staffRoles')]
    public function test_admin_and_sales_support_have_all_ticket_permissions(RoleName $role): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole($role->value);
        $ticket = SupportTicket::factory()->create();
        $policy = app(SupportTicketPolicy::class);
        $this->assertTrue($policy->viewAny($staff));
        $this->assertTrue($policy->view($staff, $ticket));
        $this->assertTrue($policy->reply($staff, $ticket));
        $this->assertTrue($policy->manage($staff, $ticket));
        $this->assertTrue($policy->replyAsStaff($staff, $ticket));
    }

    #[DataProvider('excludedRoles')]
    public function test_instructor_and_content_manager_have_no_global_ticket_permissions(RoleName $role): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role->value);
        $ticket = SupportTicket::factory()->create();
        $policy = app(SupportTicketPolicy::class);
        $this->assertFalse($policy->viewAny($user));
        $this->assertFalse($policy->view($user, $ticket));
        $this->assertFalse($policy->reply($user, $ticket));
        $this->assertFalse($policy->manage($user, $ticket));
        $this->assertFalse($policy->replyAsStaff($user, $ticket));
    }

    public static function staffRoles(): array
    {
        return ['admin' => [RoleName::Admin], 'sales support' => [RoleName::SalesSupport]];
    }

    public static function excludedRoles(): array
    {
        return ['instructor' => [RoleName::Instructor], 'content manager' => [RoleName::ContentManager]];
    }
}
