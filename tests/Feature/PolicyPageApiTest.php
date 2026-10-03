<?php

namespace Tests\Feature;

use App\Models\PolicyPage;
use App\Models\User;
use App\RoleName;
use Database\Seeders\PolicyPageSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PolicyPageApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unpublished_pages_are_not_public_and_only_expected_keys_are_seeded(): void
    {
        $this->seed(PolicyPageSeeder::class);
        $this->assertSame(3, PolicyPage::count());
        $this->getJson('/api/v1/policies/privacy?locale=ar')->assertNotFound();
        $this->getJson('/api/v1/policies/unknown?locale=ar')->assertNotFound();
    }

    public function test_content_manager_may_edit_drafts_but_cannot_publish(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PolicyPageSeeder::class);
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::ContentManager->value);
        $this->actingAs($manager);

        $this->putJson('/api/v1/admin/policy-pages/privacy', ['draft_ar' => 'نص مسودة', 'draft_en' => 'Draft text'])
            ->assertOk()->assertJsonPath('data.draft_en', 'Draft text');
        $this->postJson('/api/v1/admin/policy-pages/privacy/publication')->assertForbidden();
        $this->getJson('/api/v1/policies/privacy?locale=en')->assertNotFound();
    }

    public function test_admin_can_publish_approved_plain_text_in_both_locales_without_exposing_drafts(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PolicyPageSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $this->actingAs($admin);

        $this->postJson('/api/v1/admin/policy-pages/privacy/publication')->assertUnprocessable();
        $this->putJson('/api/v1/admin/policy-pages/privacy', [
            'draft_ar' => '<script>bad</script> نص', 'draft_en' => 'Approved text',
        ])->assertOk();
        $this->postJson('/api/v1/admin/policy-pages/privacy/publication')->assertOk()->assertJsonPath('data.version', 1);
        $this->getJson('/api/v1/policies/privacy?locale=ar')->assertOk()
            ->assertJsonPath('data.body', '<script>bad</script> نص')
            ->assertJsonMissingPath('data.draft_ar');
        $this->getJson('/api/v1/policies/privacy?locale=en')->assertOk()->assertJsonPath('data.body', 'Approved text');
        $this->getJson('/api/v1/policies/privacy?locale=de')->assertNotFound();
    }

    public function test_student_and_sales_support_cannot_change_policy_copy(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PolicyPageSeeder::class);

        foreach ([RoleName::Student, RoleName::SalesSupport] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role->value);
            $this->actingAs($user);
            $this->putJson('/api/v1/admin/policy-pages/privacy', ['draft_ar' => 'bad'])->assertForbidden();
        }

        $this->assertNull(PolicyPage::where('slug', 'privacy')->value('draft_ar'));
    }
}
