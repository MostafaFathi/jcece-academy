<?php

namespace Tests\Feature;

use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthLifecycleApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_registration_creates_only_a_student_with_normalized_email(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'New Learner', 'email' => '  NEW@example.test  ',
            'password' => 'secure-password-123', 'password_confirmation' => 'secure-password-123',
            'roles' => [RoleName::Admin->value], 'status' => 'blocked',
        ])->assertCreated()->assertJsonPath('data.email', 'new@example.test')
            ->assertJsonPath('data.roles.0', RoleName::Student->value)
            ->assertJsonMissingPath('data.password');

        $user = User::findOrFail($response->json('data.id'));
        $this->assertTrue($user->hasRole(RoleName::Student->value));
        $this->assertFalse($user->hasRole(RoleName::Admin->value));
        $this->assertTrue(Hash::check('secure-password-123', $user->password));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_registration_rejects_duplicate_email_and_invalid_fields(): void
    {
        User::factory()->create(['email' => 'existing@example.test']);
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Other', 'email' => 'EXISTING@example.test',
            'password' => 'secure-password-123', 'password_confirmation' => 'secure-password-123',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->postJson('/api/v1/auth/register', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_forgot_response_is_neutral_and_reset_token_is_delivered_only_by_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'student@example.test']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'STUDENT@example.test'])
            ->assertAccepted()->assertJsonPath('message', 'If an account exists, a reset link will be sent.')
            ->assertJsonMissingPath('token');
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.test'])
            ->assertAccepted()->assertJsonPath('message', 'If an account exists, a reset link will be sent.');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_valid_reset_changes_password_and_token_cannot_be_reused(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'old-password']);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertAccepted();
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'];
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertOk();
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertUnprocessable()->assertJsonValidationErrors('token');
    }

    public function test_invalid_reset_token_does_not_change_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email, 'token' => 'invalid',
            'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ])->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_expired_reset_token_is_rejected(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'old-password']);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertAccepted();
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        $this->travel(61)->minutes();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email, 'token' => $token,
            'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ])->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson('/api/v1/auth/register', [])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/register', [])->assertStatus(429);
    }

    public function test_recovery_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => 'missing@example.test'])->assertAccepted();
        }
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'missing@example.test'])->assertStatus(429);
    }
}
