<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\User;
use App\RoleName;
use App\Services\LessonResourceUploadService;
use App\Services\UploadedFileStorage;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class LessonResourceUploadApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_content_manager_uploads_private_file_and_downloads_without_path_leakage(): void
    {
        Storage::fake('lesson_resources');
        $this->seed(RolesAndPermissionsSeeder::class);
        $lesson = Lesson::factory()->create();
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::ContentManager->value);
        $this->actingAs($manager);

        $response = $this->postJson("/api/v1/admin/lessons/{$lesson->id}/resources/upload", [
            'title' => 'Private handout', 'file' => UploadedFile::fake()->create('handout.pdf', 5, 'application/pdf'), 'is_downloadable' => '0',
        ])->assertCreated()->assertJsonPath('data.type', 'file')->assertJsonPath('data.file_reference_available', true)->assertJsonPath('data.download_available', false)
            ->assertJsonMissingPath('data.file_path')->assertJsonMissingPath('data.storage_disk');
        $resource = LessonResource::findOrFail($response->json('data.id'));
        $this->assertStringStartsWith('lesson-resources/courses/', $resource->file_path);
        Storage::disk('lesson_resources')->assertExists(substr($resource->file_path, mb_strlen('lesson-resources/')));
        $this->getJson("/api/v1/admin/lessons/{$lesson->id}/resources/{$resource->id}/download")
            ->assertOk()->assertDownload("lesson-resource-{$resource->id}.pdf");
    }

    public function test_guest_student_and_instructor_cannot_upload_or_manage_file(): void
    {
        Storage::fake('lesson_resources');
        $this->seed(RolesAndPermissionsSeeder::class);
        $lesson = Lesson::factory()->create();
        $url = "/api/v1/admin/lessons/{$lesson->id}/resources/upload";
        $this->postJson($url, ['title' => 'File', 'file' => UploadedFile::fake()->create('handout.pdf', 5, 'application/pdf')])->assertUnauthorized();

        foreach ([RoleName::Student, RoleName::Instructor, RoleName::SalesSupport] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role->value);
            $this->actingAs($user);
            $this->postJson($url, ['title' => 'File', 'file' => UploadedFile::fake()->create('handout.pdf', 5, 'application/pdf')])->assertForbidden();
        }
        $this->assertDatabaseCount('lesson_resources', 0);
    }

    public function test_upload_validation_rejects_missing_or_disallowed_file(): void
    {
        Storage::fake('lesson_resources');
        $this->seed(RolesAndPermissionsSeeder::class);
        $lesson = Lesson::factory()->create();
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::ContentManager->value);
        $this->actingAs($manager);
        $url = "/api/v1/admin/lessons/{$lesson->id}/resources/upload";

        $this->postJson($url, ['title' => 'File'])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson($url, ['title' => 'File', 'file' => UploadedFile::fake()->create('danger.html', 5, 'text/html')])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('lesson_resources', 0);
    }

    public function test_database_failure_removes_newly_stored_bytes(): void
    {
        Storage::fake('lesson_resources');
        $lesson = Lesson::factory()->create();
        DB::shouldReceive('transaction')->once()->andThrow(new RuntimeException('Database unavailable'));

        try {
            app(LessonResourceUploadService::class)->store($lesson, UploadedFile::fake()->create('handout.pdf', 5, 'application/pdf'), 'Private', true);
            $this->fail('Expected failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Database unavailable', $exception->getMessage());
        }

        $this->assertSame([], Storage::disk('lesson_resources')->allFiles());
    }

    public function test_readable_temporary_file_is_used_when_realpath_is_unavailable(): void
    {
        Storage::fake('lesson_resources');
        $lesson = Lesson::factory()->create();
        $temporary = UploadedFile::fake()->create('guide.pdf', 1, 'application/pdf');
        $file = new class($temporary->getPathname()) extends UploadedFile
        {
            public function __construct(string $path)
            {
                parent::__construct($path, 'guide.pdf', 'application/pdf', null, true);
            }

            public function getRealPath(): string|false
            {
                return false;
            }
        };

        $resource = app(LessonResourceUploadService::class)->store($lesson, $file, 'Guide', true);
        Storage::disk('lesson_resources')->assertExists(substr($resource->file_path, mb_strlen('lesson-resources/')));
    }

    public function test_missing_temporary_file_does_not_create_resource(): void
    {
        Storage::fake('lesson_resources');
        $lesson = Lesson::factory()->create();
        Storage::disk('lesson_resources')->put('vanished.pdf', 'content');
        $file = new UploadedFile(Storage::disk('lesson_resources')->path('vanished.pdf'), 'vanished.pdf', 'application/pdf', null, true);
        Storage::disk('lesson_resources')->delete('vanished.pdf');

        try {
            app(LessonResourceUploadService::class)->store($lesson, $file, 'Guide', true);
            $this->fail('Missing upload must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }
        $this->assertDatabaseCount('lesson_resources', 0);
    }

    public function test_storage_failure_does_not_create_resource(): void
    {
        Storage::fake('lesson_resources');
        $lesson = Lesson::factory()->create();
        $this->mock(UploadedFileStorage::class)->shouldReceive('store')->once()->andThrow(new RuntimeException('Storage unavailable'));

        $this->expectException(RuntimeException::class);
        try {
            app(LessonResourceUploadService::class)->store($lesson, UploadedFile::fake()->create('guide.pdf', 1, 'application/pdf'), 'Guide', true);
        } finally {
            $this->assertDatabaseCount('lesson_resources', 0);
            $this->assertSame([], Storage::disk('lesson_resources')->allFiles());
        }
    }

    public function test_management_download_rejects_wrong_lesson_and_unprivileged_user(): void
    {
        Storage::fake('lesson_resources');
        $this->seed(RolesAndPermissionsSeeder::class);
        $lesson = Lesson::factory()->create();
        $other = Lesson::factory()->create();
        Storage::disk('lesson_resources')->put('worksheet.pdf', 'Private bytes');
        $resource = LessonResource::factory()->for($lesson)->create(['file_path' => 'lesson-resources/worksheet.pdf']);
        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        $this->actingAs($student);
        $this->getJson("/api/v1/admin/lessons/{$lesson->id}/resources/{$resource->id}/download")->assertForbidden();

        $manager = User::factory()->create();
        $manager->assignRole(RoleName::ContentManager->value);
        $this->actingAs($manager);
        $this->getJson("/api/v1/admin/lessons/{$other->id}/resources/{$resource->id}/download")->assertNotFound();
    }
}
