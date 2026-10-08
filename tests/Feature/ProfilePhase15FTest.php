<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProfilePhase15FTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_profile_requires_authentication_and_rejects_role_escalation(): void
    {
        $this->getJson('/api/v1/me/profile')->assertUnauthorized();
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $this->patchJson('/api/v1/me/profile', ['role' => 'admin', 'name' => 'Changed'])->assertUnprocessable();
        $this->assertSame($user->name, $user->refresh()->name);
    }

    public function test_owner_can_update_only_allowlisted_profile_fields(): void
    {
        $user = User::factory()->create(['email' => 'unchanged@example.test']);
        $this->actingAs($user, 'web');
        $this->patchJson('/api/v1/me/profile', ['name' => 'Updated Student', 'city' => 'Nablus'])
            ->assertOk()->assertJsonPath('data.name', 'Updated Student')->assertJsonPath('data.city', 'Nablus');
        $this->patchJson('/api/v1/me/profile', ['email' => 'stolen@example.test'])->assertUnprocessable();
        $this->assertSame('unchanged@example.test', $user->refresh()->email);
    }

    public function test_avatar_upload_uses_generated_name_and_rejects_non_images(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $this->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->create('payload.php', 3, 'application/x-php')])->assertUnprocessable();
        $this->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('original.png', 64, 64)])
            ->assertOk()->assertJsonStructure(['data' => ['avatar']]);
        $this->assertStringNotContainsString('original.png', $user->refresh()->avatar);
        $this->deleteJson('/api/v1/me/avatar')->assertOk()->assertJsonPath('data.avatar', null);
        Storage::disk('public')->assertDirectoryEmpty('avatars');
    }

    public function test_replacing_avatar_uses_shared_uploaded_file_storage_and_deletes_previous_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $this->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('first.png', 64, 64)])
            ->assertOk();
        $firstPath = Str::after(parse_url($user->refresh()->avatar, PHP_URL_PATH), '/storage/');
        Storage::disk('public')->assertExists($firstPath);

        $this->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('second.png', 64, 64)])
            ->assertOk();
        $secondPath = Str::after(parse_url($user->refresh()->avatar, PHP_URL_PATH), '/storage/');

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
        $this->assertNotSame($firstPath, $secondPath);
    }

    public function test_password_change_requires_current_password_and_removes_other_database_sessions(): void
    {
        config()->set('session.driver', 'database');
        $user = User::factory()->create(['password' => Hash::make('OldPassword123!')]);
        DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($user, 'web');

        $this->putJson('/api/v1/me/password', ['current_password' => 'wrong', 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])->assertUnprocessable();
        $this->putJson('/api/v1/me/password', ['current_password' => 'OldPassword123!', 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])->assertOk();
        $this->assertTrue(Hash::check('NewPassword123!', $user->refresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);
        $this->getJson('/api/v1/me/profile')->assertOk();
    }

    public function test_arabic_and_english_validation_follow_request_locale(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $arabic = $this->withHeader('X-Locale', 'ar')->patchJson('/api/v1/me/profile', ['name' => '']);
        $english = $this->withHeader('X-Locale', 'en')->patchJson('/api/v1/me/profile', ['name' => '']);

        $arabic->assertUnprocessable();
        $english->assertUnprocessable();
        $this->assertNotSame($arabic->json('errors.name.0'), $english->json('errors.name.0'));
    }

    public function test_login_failure_is_localized_without_disclosing_account_existence(): void
    {
        $arabic = $this->withHeader('X-Locale', 'ar')->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'WrongPassword123!']);
        $english = $this->withHeader('X-Locale', 'en')->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'WrongPassword123!']);

        $arabic->assertUnprocessable();
        $english->assertUnprocessable();
        $this->assertNotSame($arabic->json('errors.email.0'), $english->json('errors.email.0'));
    }
}
