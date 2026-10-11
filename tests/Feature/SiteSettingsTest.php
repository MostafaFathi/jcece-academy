<?php

namespace Tests\Feature;

use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SiteSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_homepage_autoplay_defaults_on_and_can_be_changed_by_site_manager(): void
    {
        $this->getJson('/api/v1/site-settings')->assertOk()->assertJsonPath('data.home_autoplay_enabled', true);
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $this->actingAs($admin, 'web')->putJson('/api/v1/admin/site-settings', ['home_autoplay_enabled' => false])
            ->assertOk()->assertJsonPath('data.home_autoplay_enabled', false);
        $this->getJson('/api/v1/site-settings')->assertOk()->assertJsonPath('data.home_autoplay_enabled', false);
        $this->getJson('/api/v1/admin/site-content')->assertOk()->assertJsonPath('data.settings.home_autoplay_enabled', false);
        $this->putJson('/api/v1/admin/site-settings', ['home_autoplay_enabled' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('home_autoplay_enabled');
    }

    public function test_student_cannot_change_homepage_settings(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);

        $this->actingAs($student, 'web')->putJson('/api/v1/admin/site-settings', ['home_autoplay_enabled' => false])->assertForbidden();
        $this->getJson('/api/v1/site-settings')->assertJsonPath('data.home_autoplay_enabled', true);
    }
}
