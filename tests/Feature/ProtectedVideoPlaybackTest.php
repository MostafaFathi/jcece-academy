<?php

namespace Tests\Feature;

use App\Contracts\ProtectedVideoProvider;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\Lesson;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProtectedVideoPlaybackTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_current_course_access_receives_short_lived_protected_playback(): void
    {
        config()->set('jcec.protected_video.allowed_playback_hosts', ['media.example.test']);
        $this->app->bind(ProtectedVideoProvider::class, fn (): ProtectedVideoProvider => new class implements ProtectedVideoProvider
        {
            public function issuePlayback(string $assetKey, CarbonImmutable $expiresAt): array
            {
                return ['url' => 'https://media.example.test/playback?token=short-lived', 'expires_at' => $expiresAt];
            }
        });

        $student = User::factory()->create();
        Role::findOrCreate('student', 'web');
        $student->assignRole('student');
        $course = Course::factory()->published()->create();
        $lesson = Lesson::factory()->published()->video()->for(CourseSection::factory()->for($course), 'section')->create([
            'video_url' => 'https://public.example.test/permanent.mp4',
            'protected_video_asset_key' => 'private/asset-1',
        ]);
        $endpoint = "/api/v1/me/courses/{$course->slug}/lessons/{$lesson->id}/protected-playback";
        $this->postJson($endpoint)->assertUnauthorized();
        $this->actingAs($student)->postJson($endpoint)->assertForbidden();

        $enrollment = Enrollment::factory()->for($student)->for($course)->create();
        EnrollmentAccessGrant::factory()->for($enrollment)->create();
        $response = $this->actingAs($student)->postJson($endpoint)->assertOk()
            ->assertJsonPath('data.url', 'https://media.example.test/playback?token=short-lived');
        $response->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringNotContainsString('private/asset-1', $response->getContent());
        $this->assertStringNotContainsString('public.example.test', $response->getContent());

        $other = Lesson::factory()->published()->video()->for(CourseSection::factory()->for(Course::factory()), 'section')->create(['protected_video_asset_key' => 'other']);
        $this->actingAs($student)->postJson("/api/v1/me/courses/{$course->slug}/lessons/{$other->id}/protected-playback")->assertNotFound();
        $enrollment->update(['status' => 'suspended']);
        $this->actingAs($student)->postJson($endpoint)->assertForbidden();
        $enrollment->update(['status' => 'active']);
        $enrollment->accessGrants()->update(['access_expires_at' => now()->subSecond()]);
        $this->actingAs($student)->postJson($endpoint)->assertForbidden();
    }

    public function test_unconfigured_provider_fails_closed_and_paid_legacy_url_is_not_exposed(): void
    {
        $student = User::factory()->create();
        Role::findOrCreate('student', 'web');
        $student->assignRole('student');
        $course = Course::factory()->published()->create();
        $lesson = Lesson::factory()->published()->video()->for(CourseSection::factory()->for($course), 'section')->create([
            'video_url' => 'https://public.example.test/permanent.mp4',
            'protected_video_asset_key' => 'private/asset-1',
        ]);
        $enrollment = Enrollment::factory()->for($student)->for($course)->create();
        EnrollmentAccessGrant::factory()->for($enrollment)->create();

        $this->actingAs($student)->getJson("/api/v1/me/courses/{$course->slug}/learn")
            ->assertOk()->assertJsonPath('data.curriculum.0.lessons.0.video_url', null);
        $this->actingAs($student)->postJson("/api/v1/me/courses/{$course->slug}/lessons/{$lesson->id}/protected-playback")
            ->assertStatus(503);
    }
}
