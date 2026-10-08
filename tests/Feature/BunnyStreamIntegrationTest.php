<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\Lesson;
use App\Models\User;
use App\Services\BunnyProtectedVideoProvider;
use App\Services\BunnyStreamClient;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BunnyStreamIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const GUID = '123e4567-e89b-42d3-a456-426614174000';

    public function test_missing_configuration_fails_closed_without_outbound_request(): void
    {
        [$user, $lesson] = $this->authorAndLesson();
        Http::preventStrayRequests();
        Sanctum::actingAs($user);

        $this->postJson($this->base($lesson), $this->payload())->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_upload_creation_is_authorized_idempotent_and_does_not_expose_library_key(): void
    {
        $this->configureBunny();
        [$user, $lesson] = $this->authorAndLesson();
        Http::preventStrayRequests();
        Http::fake(['video.bunnycdn.com/library/773691/videos' => Http::response(['guid' => self::GUID], 200)]);
        Sanctum::actingAs($user);

        $first = $this->postJson($this->base($lesson), $this->payload())->assertCreated()
            ->assertJsonPath('data.status', 'uploading')
            ->assertJsonPath('data.upload.video_id', self::GUID);
        $second = $this->postJson($this->base($lesson), $this->payload())->assertCreated();
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, $lesson->videoUploads()->count());
        Http::assertSentCount(1);
        $this->assertStringNotContainsString('test-library-key', $first->getContent());
        $this->assertStringNotContainsString('test-token-key', $first->getContent());
        $this->assertSame(hash('sha256', '773691'.'test-library-key'.$first->json('data.upload.authorization_expire').self::GUID), $first->json('data.upload.authorization_signature'));
    }

    public function test_invalid_upload_and_cross_course_author_are_denied_before_provider_call(): void
    {
        $this->configureBunny();
        [$user, $lesson] = $this->authorAndLesson();
        Http::preventStrayRequests();
        Sanctum::actingAs($user);

        $this->postJson($this->base($lesson), array_merge($this->payload(), ['filename' => 'bad.php']))->assertUnprocessable()->assertJsonValidationErrors('filename');
        $other = Lesson::factory()->video()->for(CourseSection::factory()->for(Course::factory()), 'section')->create();
        $user->syncRoles(['instructor']);
        $user->givePermissionTo('curriculum.update');
        $this->postJson($this->base($other), $this->payload())->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_paid_video_draft_has_no_manual_asset_key_or_public_media_url(): void
    {
        [$user, $lesson] = $this->authorAndLesson();
        Sanctum::actingAs($user);
        $section = $lesson->section;

        $draftId = $this->postJson("/api/v1/admin/sections/{$section->id}/lessons", [
            'title' => 'Private video', 'slug' => 'private-video', 'type' => 'video', 'is_preview' => false,
        ])->assertCreated()->assertJsonPath('data.protected_playback_available', false)->json('data.id');
        $this->patchJson("/api/v1/admin/sections/{$section->id}/lessons/{$draftId}", ['is_published' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('is_published');
        $this->postJson("/api/v1/admin/sections/{$section->id}/lessons", [
            'title' => 'Published empty video', 'slug' => 'empty-video', 'type' => 'video', 'is_published' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('is_published');
        $this->postJson("/api/v1/admin/sections/{$section->id}/lessons", [
            'title' => 'Forged private video', 'slug' => 'forged-video', 'type' => 'video', 'protected_video_asset_key' => self::GUID,
        ])->assertUnprocessable()->assertJsonValidationErrors('protected_video_asset_key');
    }

    public function test_processing_is_not_playable_until_ready_and_bunny_embed_is_signed(): void
    {
        $this->configureBunny();
        [$user, $lesson] = $this->authorAndLesson();
        $lesson->section->course->update(['status' => 'published', 'published_at' => now()->subDay()]);
        $lesson->update(['is_published' => true]);
        $student = User::factory()->create();
        $enrollment = Enrollment::factory()->for($student)->for($lesson->section->course)->create();
        EnrollmentAccessGrant::factory()->for($enrollment)->create();
        Http::preventStrayRequests();
        Http::fakeSequence('video.bunnycdn.com/library/773691/videos/'.self::GUID)
            ->push(['status' => 2, 'encodeProgress' => 50, 'length' => 90])
            ->push(['status' => 3, 'encodeProgress' => 100, 'length' => 90]);
        Http::fake(['video.bunnycdn.com/library/773691/videos' => Http::response(['guid' => self::GUID])]);
        Sanctum::actingAs($user);
        $uploadId = $this->postJson($this->base($lesson), $this->payload())->assertCreated()->json('data.id');
        $this->getJson($this->base($lesson).'/'.$uploadId)->assertOk()->assertJsonPath('data.status', 'processing');
        Sanctum::actingAs($student);
        $playback = "/api/v1/me/courses/{$lesson->section->course->slug}/lessons/{$lesson->id}/protected-playback";
        $this->postJson($playback)->assertUnprocessable();

        Sanctum::actingAs($user);
        $this->getJson($this->base($lesson).'/'.$uploadId)->assertOk()->assertJsonPath('data.status', 'ready');
        Sanctum::actingAs($student);
        $response = $this->postJson($playback)->assertOk()->assertJsonPath('data.player', 'bunny_embed');
        $url = $response->json('data.url');
        $this->assertStringStartsWith('https://player.mediadelivery.net/embed/773691/'.self::GUID.'?token=', $url);
        $this->assertStringNotContainsString('test-token-key', $url);
        $this->assertSame(90, $lesson->refresh()->duration_seconds);
    }

    public function test_provider_signing_uses_documented_token_and_exact_expiry(): void
    {
        $this->configureBunny();
        $expires = CarbonImmutable::parse('2026-10-08 10:00:00 UTC');
        $signed = (new BunnyProtectedVideoProvider(new BunnyStreamClient))->issuePlayback(self::GUID, $expires);
        $expected = hash('sha256', 'test-token-key'.self::GUID.$expires->timestamp);

        $this->assertSame('https://player.mediadelivery.net/embed/773691/'.self::GUID.'?token='.$expected.'&expires='.$expires->timestamp, $signed['url']);
    }

    public function test_ambiguous_provider_creation_does_not_retry_into_a_second_billable_video(): void
    {
        $this->configureBunny();
        [$user, $lesson] = $this->authorAndLesson();
        Http::preventStrayRequests();
        Http::fake(['video.bunnycdn.com/library/773691/videos' => Http::response([], 503)]);
        Sanctum::actingAs($user);

        $this->postJson($this->base($lesson), $this->payload())->assertStatus(503);
        $this->postJson($this->base($lesson), $this->payload())->assertStatus(409);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('lesson_video_uploads', ['lesson_id' => $lesson->id, 'status' => 'uncertain', 'video_guid' => null]);
    }

    public function test_remote_delete_failure_revokes_playback_and_remains_retriable(): void
    {
        $this->configureBunny();
        [$user, $lesson] = $this->authorAndLesson();
        $lesson->update(['video_provider' => 'bunny_stream', 'protected_video_asset_key' => self::GUID]);
        $upload = $lesson->videoUploads()->create([
            'uploaded_by' => $user->id,
            'request_id' => $this->payload()['request_id'],
            'video_guid' => self::GUID,
            'filename' => 'lesson.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 1024,
            'status' => 'ready',
            'is_current' => true,
        ]);
        Http::preventStrayRequests();
        Http::fake(['video.bunnycdn.com/library/773691/videos/'.self::GUID => Http::sequence()->push([], 500)->push([], 204)]);
        Sanctum::actingAs($user);

        $this->deleteJson($this->base($lesson).'/'.$upload->id)->assertOk()->assertJsonPath('data.status', 'cleanup_failed');
        $this->assertNull($lesson->refresh()->protected_video_asset_key);
        $this->deleteJson($this->base($lesson).'/'.$upload->id)->assertOk()->assertJsonPath('data.status', 'deleted');
        Http::assertSentCount(2);
    }

    public function test_draft_course_and_unpublished_lesson_never_issue_signed_playback(): void
    {
        $this->configureBunny();
        [$author, $lesson] = $this->authorAndLesson();
        $student = User::factory()->create();
        $enrollment = Enrollment::factory()->for($student)->for($lesson->section->course)->create();
        EnrollmentAccessGrant::factory()->for($enrollment)->create();
        $lesson->update(['video_provider' => 'bunny_stream', 'protected_video_asset_key' => self::GUID]);
        $lesson->videoUploads()->create([
            'uploaded_by' => $author->id, 'request_id' => $this->payload()['request_id'], 'video_guid' => self::GUID,
            'filename' => 'lesson.mp4', 'mime_type' => 'video/mp4', 'size_bytes' => 1024, 'status' => 'ready', 'is_current' => true,
        ]);
        $path = "/api/v1/me/courses/{$lesson->section->course->slug}/lessons/{$lesson->id}/protected-playback";
        Sanctum::actingAs($student);

        $lesson->update(['is_published' => true]);
        $this->postJson($path)->assertNotFound();
        $lesson->section->course->update(['status' => 'published', 'published_at' => now()->subDay()]);
        $lesson->update(['is_published' => false]);
        $this->postJson($path)->assertNotFound();
    }

    public function test_course_section_and_lesson_deletion_cannot_orphan_a_bunny_upload(): void
    {
        [$author, $lesson] = $this->authorAndLesson();
        $section = $lesson->section;
        $course = $section->course;
        $lesson->videoUploads()->create([
            'uploaded_by' => $author->id, 'request_id' => $this->payload()['request_id'], 'video_guid' => self::GUID,
            'filename' => 'lesson.mp4', 'mime_type' => 'video/mp4', 'size_bytes' => 1024, 'status' => 'uploading',
        ]);
        Sanctum::actingAs($author);

        $this->deleteJson("/api/v1/admin/sections/{$section->id}/lessons/{$lesson->id}")->assertStatus(409);
        $this->deleteJson("/api/v1/admin/courses/{$course->id}/sections/{$section->id}")->assertStatus(409);
        $this->deleteJson("/api/v1/admin/courses/{$course->id}")->assertStatus(409);
        $this->assertNotNull($lesson->fresh());
    }

    public function test_replacement_keeps_old_video_until_new_video_is_ready_then_deletes_old_remote_video(): void
    {
        $this->configureBunny();
        [$author, $lesson] = $this->authorAndLesson();
        $oldGuid = '123e4567-e89b-42d3-a456-426614174001';
        $lesson->update(['video_provider' => 'bunny_stream', 'protected_video_asset_key' => $oldGuid]);
        $old = $lesson->videoUploads()->create([
            'uploaded_by' => $author->id, 'request_id' => '550e8400-e29b-41d4-a716-446655440001', 'video_guid' => $oldGuid,
            'filename' => 'old.mp4', 'mime_type' => 'video/mp4', 'size_bytes' => 100, 'status' => 'ready', 'is_current' => true,
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'video.bunnycdn.com/library/773691/videos' => Http::response(['guid' => self::GUID]),
            'video.bunnycdn.com/library/773691/videos/'.self::GUID => Http::sequence()->push(['status' => 2, 'encodeProgress' => 50])->push(['status' => 3, 'encodeProgress' => 100]),
            'video.bunnycdn.com/library/773691/videos/'.$oldGuid => Http::response([], 204),
        ]);
        Sanctum::actingAs($author);

        $newId = $this->postJson($this->base($lesson), $this->payload())->assertCreated()->json('data.id');
        $this->getJson($this->base($lesson).'/'.$newId)->assertOk()->assertJsonPath('data.status', 'processing');
        $this->assertSame($oldGuid, $lesson->refresh()->protected_video_asset_key);
        $this->getJson($this->base($lesson).'/'.$newId)->assertOk()->assertJsonPath('data.status', 'ready');
        $this->assertSame(self::GUID, $lesson->refresh()->protected_video_asset_key);
        $this->assertSame('deleted', $old->refresh()->status);
        Http::assertSent(fn ($request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/'.$oldGuid));
    }

    public function test_nested_upload_status_does_not_disclose_another_lessons_video(): void
    {
        [$author, $lesson] = $this->authorAndLesson();
        $other = Lesson::factory()->video()->for(CourseSection::factory()->for($lesson->section->course), 'section')->create();
        $upload = $other->videoUploads()->create([
            'uploaded_by' => $author->id, 'request_id' => $this->payload()['request_id'], 'video_guid' => self::GUID,
            'filename' => 'lesson.mp4', 'mime_type' => 'video/mp4', 'size_bytes' => 1024, 'status' => 'ready', 'is_current' => true,
        ]);
        Sanctum::actingAs($author);

        $this->getJson($this->base($lesson).'/'.$upload->id)->assertNotFound();
    }

    public function test_failed_processing_must_be_cleaned_before_another_upload(): void
    {
        $this->configureBunny();
        [$author, $lesson] = $this->authorAndLesson();
        Http::preventStrayRequests();
        Http::fake([
            'video.bunnycdn.com/library/773691/videos' => Http::response(['guid' => self::GUID]),
            'video.bunnycdn.com/library/773691/videos/'.self::GUID => Http::response(['status' => 5, 'encodeProgress' => 27]),
        ]);
        Sanctum::actingAs($author);

        $id = $this->postJson($this->base($lesson), $this->payload())->assertCreated()->json('data.id');
        $this->getJson($this->base($lesson).'/'.$id)->assertOk()->assertJsonPath('data.status', 'failed');
        $this->assertNull($lesson->refresh()->protected_video_asset_key);
        $this->postJson($this->base($lesson), array_merge($this->payload(), ['request_id' => '550e8400-e29b-41d4-a716-446655440002']))->assertStatus(409);
        Http::assertSentCount(2);
    }

    /** @return array{User, Lesson} */
    private function authorAndLesson(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $author = User::factory()->create();
        $author->assignRole('content_manager');
        $course = Course::factory()->for($author, 'instructor')->create();
        $lesson = Lesson::factory()->video()->for(CourseSection::factory()->for($course), 'section')->create(['is_preview' => false]);

        return [$author, $lesson];
    }

    private function configureBunny(): void
    {
        config()->set('jcec.bunny_stream', [
            'enabled' => true,
            'library_id' => '773691',
            'api_key' => 'test-library-key',
            'token_key' => 'test-token-key',
            'cdn_host' => 'vz-a277a1c3-8b6.b-cdn.net',
            'api_endpoint' => 'https://video.bunnycdn.com',
            'playback_ttl_seconds' => 120,
            'upload_signature_ttl_seconds' => 86400,
            'max_upload_megabytes' => 2048,
        ]);
    }

    /** @return array<string, string|int> */
    private function payload(): array
    {
        return ['request_id' => '550e8400-e29b-41d4-a716-446655440000', 'filename' => 'lesson.mp4', 'mime_type' => 'video/mp4', 'size_bytes' => 1024];
    }

    private function base(Lesson $lesson): string
    {
        return "/api/v1/admin/lessons/{$lesson->id}/video-uploads";
    }
}
