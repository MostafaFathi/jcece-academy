<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SiteContentPhase15FTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_contact_rejects_oversize_payloads_and_stores_plain_text(): void
    {
        $payload = ['name' => 'Visitor', 'email' => 'visitor@example.test', 'subject' => 'Question', 'message' => '<script>alert(1)</script> Please reply.'];
        $this->postJson('/api/v1/contact', [...$payload, 'message' => str_repeat('A', 5001)])->assertUnprocessable();
        $this->postJson('/api/v1/contact', $payload)->assertCreated()->assertJsonPath('status', 'received');
        $this->assertDatabaseHas('contact_messages', ['email' => 'visitor@example.test', 'message' => $payload['message']]);
    }

    public function test_contact_messages_are_private_to_site_content_administrators(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ContactMessage::factory()->create();
        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        $this->actingAs($student, 'web')->getJson('/api/v1/admin/contact-messages')->assertForbidden();
    }

    public function test_admin_can_read_contact_messages(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ContactMessage::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $this->actingAs($admin, 'web')->getJson('/api/v1/admin/contact-messages')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_admin_cannot_publish_empty_editorial_copy(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $this->actingAs($admin, 'web')->postJson('/api/v1/admin/site-pages/about/publication')->assertUnprocessable();

        $this->getJson('/api/v1/site-pages/about')->assertNotFound();
    }
}
