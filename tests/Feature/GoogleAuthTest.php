<?php

namespace Tests\Feature;

use App\Models\User;
use App\RoleName;
use App\UserStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.google', [
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
            'redirect_uri' => 'https://jcec-academy.local/auth/google/callback',
        ]);
    }

    public function test_redirect_includes_state_pkce_and_exact_callback(): void
    {
        $response = $this->get('/auth/google/redirect?locale=en&redirect=%2Fcourses%2Fdemo');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertSame('accounts.google.com', parse_url($location, PHP_URL_HOST));
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('https://jcec-academy.local/auth/google/callback', $query['redirect_uri']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame('openid email profile', $query['scope']);
        $this->assertSame(session('google_oauth.state'), $query['state']);
        $this->assertSame('/courses/demo', session('google_oauth.destination'));
    }

    public function test_verified_google_identity_creates_and_signs_in_student(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->fakeGoogleProfile();
        $state = $this->startGoogle();

        $this->get('/auth/google/callback?state='.$state.'&code=valid-code')->assertRedirect('/student');

        $user = User::where('email', 'learner@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole(RoleName::Student->value));
        $this->assertSame('google-sub-1', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->last_login_at);
        Http::assertSentCount(2);
    }

    public function test_new_google_student_returns_to_cart_with_registration_notice(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->fakeGoogleProfile();
        $this->get('/auth/google/redirect?redirect=%2Fstudent%2Fcart')->assertRedirect();
        $state = session('google_oauth.state');

        $this->get('/auth/google/callback?state='.$state.'&code=valid-code')
            ->assertRedirect('/student/cart?welcome=registered');

        $this->assertAuthenticated();
    }

    public function test_existing_google_student_returns_to_cart_with_login_notice(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $student = User::factory()->create(['email' => 'learner@gmail.com', 'google_id' => 'google-sub-1']);
        $student->assignRole(RoleName::Student->value);
        $this->fakeGoogleProfile(['email' => 'learner@gmail.com']);
        $this->get('/auth/google/redirect?redirect=%2Fstudent%2Fcart')->assertRedirect();
        $state = session('google_oauth.state');

        $this->get('/auth/google/callback?state='.$state.'&code=valid-code')
            ->assertRedirect('/student/cart?welcome=login');

        $this->assertAuthenticatedAs($student);
    }

    public function test_existing_student_is_linked_without_duplicate_account(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $student = User::factory()->create(['email' => 'learner@gmail.com']);
        $student->assignRole(RoleName::Student->value);
        $this->fakeGoogleProfile(['email' => 'learner@gmail.com']);
        $state = $this->startGoogle();

        $this->get('/auth/google/callback?state='.$state.'&code=valid-code')->assertRedirect('/student');

        $this->assertAuthenticatedAs($student);
        $this->assertSame('google-sub-1', $student->fresh()->google_id);
        $this->assertSame(1, User::where('email', 'learner@gmail.com')->count());
    }

    public function test_non_authoritative_external_email_cannot_auto_link_existing_student(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $student = User::factory()->create(['email' => 'learner@example.com']);
        $student->assignRole(RoleName::Student->value);
        $this->fakeGoogleProfile();
        $state = $this->startGoogle();

        $this->get('/auth/google/callback?state='.$state.'&code=valid-code')->assertRedirect('/login?google_error=account');

        $this->assertGuest();
        $this->assertNull($student->fresh()->google_id);
    }

    public function test_google_workspace_identity_can_link_existing_student(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $student = User::factory()->create(['email' => 'learner@example.com']);
        $student->assignRole(RoleName::Student->value);
        $this->fakeGoogleProfile(['hd' => 'example.com']);
        $state = $this->startGoogle();

        $this->get('/auth/google/callback?state='.$state.'&code=valid-code')->assertRedirect('/student');

        $this->assertAuthenticatedAs($student);
        $this->assertSame('google-sub-1', $student->fresh()->google_id);
    }

    public function test_google_cannot_sign_in_staff_account_with_same_email(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $staff = User::factory()->create(['email' => 'learner@example.com']);
        $staff->assignRole(RoleName::Admin->value);
        $this->fakeGoogleProfile();
        $state = $this->startGoogle();

        $this->get('/auth/google/callback?state='.$state.'&code=valid-code')->assertRedirect('/login?google_error=account');

        $this->assertGuest();
        $this->assertNull($staff->fresh()->google_id);
    }

    public function test_google_cannot_sign_in_blocked_student(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $student = User::factory()->create(['email' => 'learner@example.com', 'status' => UserStatus::Blocked]);
        $student->assignRole(RoleName::Student->value);
        $this->fakeGoogleProfile();
        $state = $this->startGoogle();

        $this->get('/auth/google/callback?state='.$state.'&code=valid-code')->assertRedirect('/login?google_error=account');

        $this->assertGuest();
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $this->fakeGoogleProfile(['email_verified' => false]);
        $state = $this->startGoogle();

        $this->get('/auth/google/callback?state='.$state.'&code=valid-code')->assertRedirect('/login?google_error=failed');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_invalid_state_is_rejected_before_contacting_google(): void
    {
        Http::preventStrayRequests();
        $this->startGoogle();

        $this->get('/auth/google/callback?state=wrong&code=valid-code')->assertRedirect('/login?google_error=failed');

        Http::assertNothingSent();
        $this->assertGuest();
        $this->assertNull(session('google_oauth'));
    }

    public function test_expired_state_is_rejected_before_contacting_google(): void
    {
        Http::preventStrayRequests();
        $state = $this->startGoogle();
        $this->travel(11)->minutes();

        $this->get('/auth/google/callback?state='.$state.'&code=valid-code')->assertRedirect('/login?google_error=failed');

        Http::assertNothingSent();
        $this->assertGuest();
    }

    public function test_google_callback_cannot_be_replayed(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->fakeGoogleProfile();
        $state = $this->startGoogle();
        $this->get('/auth/google/callback?state='.$state.'&code=valid-code')->assertRedirect('/student');

        $this->get('/auth/google/callback?state='.$state.'&code=valid-code')->assertRedirect('/login?google_error=failed');

        Http::assertSentCount(2);
    }

    public function test_google_token_failure_does_not_create_a_user(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);
        $state = $this->startGoogle();

        $this->get('/auth/google/callback?state='.$state.'&code=bad-code')->assertRedirect('/login?google_error=failed');

        $this->assertGuest();
        $this->assertSame(0, User::count());
        Http::assertSentCount(1);
    }

    public function test_google_redirect_is_unavailable_on_a_different_domain(): void
    {
        config()->set('services.google.redirect_uri', 'https://other.example/auth/google/callback');

        $this->get('/auth/google/redirect')->assertRedirect('/login?google_error=unavailable');

        $this->assertNull(session('google_oauth'));
    }

    public function test_external_destination_is_not_used_after_google_login(): void
    {
        $this->get('/auth/google/redirect?redirect=%2F%2Fevil.example');

        $this->assertSame('/student', session('google_oauth.destination'));
    }

    /** @param array<string, mixed> $overrides */
    private function fakeGoogleProfile(array $overrides = []): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access-token']),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response(array_merge([
                'sub' => 'google-sub-1',
                'email' => 'learner@example.com',
                'email_verified' => true,
                'name' => 'Test Learner',
            ], $overrides)),
        ]);
    }

    private function startGoogle(): string
    {
        $this->get('/auth/google/redirect')->assertRedirect();

        return session('google_oauth.state');
    }
}
