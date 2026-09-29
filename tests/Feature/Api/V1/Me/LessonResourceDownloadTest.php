<?php

namespace Tests\Feature\Api\V1\Me;

use App\Http\Resources\Api\V1\LessonResourceResource;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LessonResourceDownloadTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authorized_download_streams_private_bytes_with_a_server_filename(): void
    {
        [$user, $course, , $lesson, $resource] = $this->fixture();
        Sanctum::actingAs($user);

        $response = $this->getJson($this->url($course, $lesson, $resource))
            ->assertOk()
            ->assertDownload("lesson-resource-{$resource->id}.pdf")
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertSame('Private worksheet bytes', $response->streamedContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('worksheet', $response->headers->get('Content-Disposition'));
        $this->assertFalse(config('filesystems.disks.lesson_resources.serve'));
    }

    #[DataProvider('deniedStates')]
    public function test_download_rechecks_access_for_every_request(string $state, int $status): void
    {
        [$user, $course, $enrollment, $lesson, $resource] = $this->fixture();

        if ($state === 'other_user') {
            $user = User::factory()->create();
        } elseif ($state === 'suspended') {
            $enrollment->update(['status' => 'suspended']);
        } elseif ($state === 'expired') {
            $enrollment->accessGrants()->update(['access_expires_at' => now()]);
        } elseif ($state === 'revoked') {
            $enrollment->accessGrants()->update(['revoked_at' => now()]);
        }

        if ($state !== 'guest') {
            Sanctum::actingAs($user);
        }

        $response = $this->getJson($this->url($course, $lesson, $resource))->assertStatus($status);
        $this->assertStringNotContainsString('Private worksheet bytes', $response->getContent());
    }

    public function test_access_revoked_after_successful_download_is_denied(): void
    {
        [$user, $course, $enrollment, $lesson, $resource] = $this->fixture();
        Sanctum::actingAs($user);
        $this->getJson($this->url($course, $lesson, $resource))->assertOk();
        $enrollment->update(['status' => 'suspended']);
        $this->getJson($this->url($course, $lesson, $resource))->assertForbidden();
    }

    public function test_cross_course_cross_lesson_and_missing_resource_ids_are_not_found(): void
    {
        [$user, $course, , $lesson, $resource] = $this->fixture();
        $otherLesson = Lesson::factory()->published()->create();
        $otherResource = LessonResource::factory()->for($otherLesson)->create();
        Sanctum::actingAs($user);

        $this->getJson($this->url($course, $otherLesson, $otherResource))->assertNotFound();
        $this->getJson($this->url($course, $lesson, $otherResource))->assertNotFound();
        $this->getJson("/api/v1/me/courses/{$course->slug}/lessons/{$lesson->id}/resources/999999/download")->assertNotFound();
    }

    #[DataProvider('unavailableFiles')]
    public function test_unavailable_or_unsafe_files_are_not_served(string $state): void
    {
        [$user, $course, , $lesson, $resource] = $this->fixture();
        match ($state) {
            'missing' => Storage::disk('lesson_resources')->delete('worksheet.pdf'),
            'disabled' => $resource->update(['is_downloadable' => false]),
            'unpublished' => $lesson->update(['is_published' => false]),
            'inactive_section' => $lesson->section->update(['is_active' => false]),
            'external_only' => $resource->update(['file_path' => null, 'external_url' => 'https://example.com/worksheet']),
            default => $resource->update(['file_path' => $state]),
        };
        Sanctum::actingAs($user);
        $this->getJson($this->url($course, $lesson, $resource))->assertNotFound();
    }

    public function test_student_and_public_payloads_never_expose_resource_storage_metadata(): void
    {
        [$user, $course, , $lesson, $resource] = $this->fixture();
        $lesson->update(['is_preview' => true]);
        $resource->update(['external_url' => 'https://example.com/storage/lesson-resources/worksheet.pdf']);
        $public = $this->getJson("/api/v1/courses/{$course->slug}")->assertOk();
        $public->assertJsonMissingPath('data.curriculum.0.lessons.0.resources');
        Sanctum::actingAs($user);
        $learning = $this->getJson("/api/v1/me/courses/{$course->slug}/learn")->assertOk();
        $learning->assertJsonPath('data.curriculum.0.lessons.0.resources.0.download_available', true)
            ->assertJsonPath('data.curriculum.0.lessons.0.resources.0.external_url', null);

        foreach ([$public->getContent(), $learning->getContent(), $resource->toJson()] as $json) {
            foreach (['file_path', 'storage_path', 'storage_disk', 'worksheet.pdf'] as $privateMetadata) {
                $this->assertStringNotContainsString($privateMetadata, $json);
            }
        }
    }

    public function test_private_resource_has_no_direct_storage_route(): void
    {
        $this->fixture();
        $this->get('/storage/lesson-resources/worksheet.pdf')->assertForbidden();
    }

    public function test_resolved_path_outside_private_disk_is_not_served(): void
    {
        [$user, $course, , $lesson, $resource] = $this->fixture();
        $root = Storage::disk('lesson_resources')->path('');
        $outside = Storage::fake('lesson_resources_outside_test');
        $outside->put('worksheet.pdf', 'Outside bytes');
        $disk = $this->createMock(FilesystemAdapter::class);
        $disk->method('path')->willReturnMap([
            ['', $root], ['worksheet.pdf', $outside->path('worksheet.pdf')],
        ]);
        $disk->expects($this->never())->method('download');
        Storage::shouldReceive('disk')->with('lesson_resources')->andReturn($disk);
        Sanctum::actingAs($user);

        $this->getJson($this->url($course, $lesson, $resource))->assertNotFound();
    }

    public function test_download_uses_private_disk_even_when_default_is_public(): void
    {
        [$user, $course, , $lesson, $resource] = $this->fixture();
        config(['filesystems.default' => 'public']);
        Sanctum::actingAs($user);

        $response = $this->getJson($this->url($course, $lesson, $resource))->assertOk();
        $this->assertSame('Private worksheet bytes', $response->streamedContent());
    }

    #[DataProvider('unsafeExternalUrls')]
    public function test_resource_metadata_suppresses_unsafe_external_urls(string $url): void
    {
        $resource = LessonResource::factory()->make(['file_path' => null, 'external_url' => $url]);
        $this->assertNull((new LessonResourceResource($resource))->resolve()['external_url']);
    }

    #[DataProvider('accessStates')]
    public function test_my_courses_returns_authoritative_access_state(string $state): void
    {
        [$user, , $enrollment] = $this->fixture();
        match ($state) {
            'expired' => $enrollment->accessGrants()->update(['access_expires_at' => now()]),
            'suspended' => $enrollment->update(['status' => 'suspended']),
            'scheduled' => $enrollment->accessGrants()->update(['access_starts_at' => now()->addDay()]),
            'revoked' => $enrollment->accessGrants()->update(['revoked_at' => now()]),
            'unavailable' => $enrollment->accessGrants()->delete(),
            default => null,
        };
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/me/courses')->assertOk()
            ->assertJsonPath('data.0.access_state', $state)
            ->assertJsonPath('data.0.has_access', $state === 'active')
            ->assertJsonPath('data.0.is_lifetime', $state === 'active');
    }

    /** @return array<string, array{string, int}> */
    public static function deniedStates(): array
    {
        return ['guest' => ['guest', 401], 'other_user' => ['other_user', 403], 'expired' => ['expired', 403], 'suspended' => ['suspended', 403], 'revoked' => ['revoked', 403]];
    }

    /** @return array<string, array{string}> */
    public static function unavailableFiles(): array
    {
        return array_combine($states = ['missing', 'disabled', 'unpublished', 'inactive_section', 'external_only', 'lesson-resources/../secret.pdf', 'lesson-resources/a/../../secret.pdf', 'lesson-resources/%2e%2e/secret.pdf', 'lesson-resources/..\\secret.pdf', 'C:/secret.pdf', '/etc/passwd', 'https://example.com/private.pdf'], array_map(fn (string $state): array => [$state], $states));
    }

    /** @return array<string, array{string}> */
    public static function unsafeExternalUrls(): array
    {
        return [['javascript:alert(1)'], ['file:///C:/secret.pdf'], ['https://example.com/storage/worksheet.pdf'], ['https://example.com/%70rivate/a.pdf'], ['https://example.com/%2570rivate/a.pdf'], ['https://example.com/a?X-Amz-Signature=secret'], ['https://user:pass@example.com/file']];
    }

    /** @return array<string, array{string}> */
    public static function accessStates(): array
    {
        return [['active'], ['expired'], ['suspended'], ['scheduled'], ['revoked'], ['unavailable']];
    }

    /** @return array{User, Course, Enrollment, Lesson, LessonResource} */
    private function fixture(): array
    {
        Storage::fake('lesson_resources');
        Storage::disk('lesson_resources')->put('worksheet.pdf', 'Private worksheet bytes');
        $user = User::factory()->create();
        $course = Course::factory()->published()->create();
        $section = CourseSection::factory()->for($course)->create();
        $lesson = Lesson::factory()->published()->for($section, 'section')->create();
        $resource = LessonResource::factory()->for($lesson)->create(['file_path' => 'lesson-resources/worksheet.pdf', 'title' => 'ورقة عمل']);
        $enrollment = Enrollment::factory()->for($user)->for($course)->create();
        EnrollmentAccessGrant::factory()->for($enrollment)->lifetime()->create();

        return [$user, $course, $enrollment, $lesson, $resource];
    }

    private function url(Course $course, Lesson $lesson, LessonResource $resource): string
    {
        return "/api/v1/me/courses/{$course->slug}/lessons/{$lesson->id}/resources/{$resource->id}/download";
    }
}
