<?php

namespace Tests\Feature\Api\V1\Me;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\Lesson;
use App\Models\LessonComment;
use App\Models\User;
use App\PermissionName;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LessonCommentApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_enrolled_students_can_comment_and_reply_within_one_lesson(): void
    {
        [$student, $course, $lesson] = $this->accessibleLesson();
        $secondStudent = User::factory()->create();
        $this->enroll($secondStudent, $course);
        Sanctum::actingAs($student);

        $first = $this->postJson($this->url($course, $lesson), ['body' => 'How do I organize these rows?'])
            ->assertCreated()->assertJsonPath('data.author.id', $student->id)->json('data.id');

        Sanctum::actingAs($secondStudent);
        $this->postJson($this->url($course, $lesson), ['body' => 'Start with a header row.', 'parent_id' => $first])
            ->assertCreated()->assertJsonPath('data.parent_id', $first);
        $this->getJson($this->url($course, $lesson))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'How do I organize these rows?')
            ->assertJsonPath('data.0.replies_count', 1)
            ->assertJsonPath('data.0.replies.0.body', 'Start with a header row.');

        $this->assertDatabaseHas('lesson_comments', ['lesson_id' => $lesson->id, 'user_id' => $secondStudent->id, 'parent_id' => $first]);
    }

    public function test_student_can_post_an_image_without_text_and_only_course_members_can_view_it(): void
    {
        [$student, $course, $lesson] = $this->accessibleLesson();
        Storage::fake('local');
        Sanctum::actingAs($student);

        $posted = $this->post($this->url($course, $lesson), ['attachment' => UploadedFile::fake()->image('diagram.png')], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.body', null)
            ->assertJsonPath('data.attachment.name', 'diagram.png')
            ->assertJsonPath('data.attachment.is_image', true);
        $comment = LessonComment::query()->findOrFail($posted->json('data.id'));
        $attachmentUrl = $posted->json('data.attachment.url');

        Storage::disk('local')->assertExists($comment->attachment_path);
        $this->get($attachmentUrl)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson($this->url($course, $lesson))->assertOk()->assertJsonPath('data.0.attachment.url', $attachmentUrl);

        Sanctum::actingAs(User::factory()->create());
        $this->get($attachmentUrl)->assertForbidden();
        $this->get($this->url($course, $lesson).'/999999/attachment')->assertNotFound();

        $student->enrollments()->firstOrFail()->update(['status' => 'suspended']);
        Sanctum::actingAs($student);
        $this->get($attachmentUrl)->assertForbidden();
    }

    public function test_image_upload_succeeds_when_windows_cannot_resolve_the_temporary_real_path(): void
    {
        [$student, $course, $lesson] = $this->accessibleLesson();
        Storage::fake('local');
        $source = UploadedFile::fake()->image('diagram.png');
        $upload = new class($source->getPathname(), 'diagram.png', 'image/png', UPLOAD_ERR_OK, true) extends UploadedFile
        {
            public function getRealPath(): string|false
            {
                return false;
            }
        };
        Sanctum::actingAs($student);

        $response = $this->post($this->url($course, $lesson), ['attachment' => $upload], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.attachment.name', 'diagram.png');

        $comment = LessonComment::query()->findOrFail($response->json('data.id'));
        Storage::disk('local')->assertExists($comment->attachment_path);
    }

    public function test_student_can_attach_a_document_to_a_reply_and_download_it_privately(): void
    {
        [$student, $course, $lesson] = $this->accessibleLesson();
        $root = LessonComment::factory()->for($lesson)->for($student)->create();
        Storage::fake('local');
        Sanctum::actingAs($student);

        $posted = $this->post($this->url($course, $lesson), [
            'body' => 'See the handout',
            'parent_id' => $root->id,
            'attachment' => UploadedFile::fake()->create('handout.pdf', 1, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.parent_id', $root->id)
            ->assertJsonPath('data.attachment.is_image', false);
        $attachmentUrl = $posted->json('data.attachment.url');

        $this->get($attachmentUrl)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->getJson($this->repliesUrl($course, $lesson, $root))->assertOk()
            ->assertJsonPath('data.0.attachment.name', 'handout.pdf');
    }

    public function test_rejects_unsupported_oversized_or_disguised_attachments(): void
    {
        [$student, $course, $lesson] = $this->accessibleLesson();
        Storage::fake('local');
        Sanctum::actingAs($student);

        foreach ([
            UploadedFile::fake()->create('script.php', 1, 'application/x-php'),
            UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf'),
            UploadedFile::fake()->image('disguised.php'),
            UploadedFile::fake()->create('broken.png', 1, 'image/png'),
        ] as $file) {
            $this->post($this->url($course, $lesson), ['attachment' => $file], ['Accept' => 'application/json'])
                ->assertUnprocessable()->assertJsonValidationErrors('attachment');
        }

        $this->assertDatabaseCount('lesson_comments', 0);
        Storage::disk('local')->assertCount('lesson-comments', 0);
    }

    public function test_discussions_are_isolated_by_lesson_and_course(): void
    {
        [$student, $course, $lesson] = $this->accessibleLesson();
        $otherLesson = Lesson::factory()->published()->for($lesson->section, 'section')->create();
        $otherCourseLesson = Lesson::factory()->published()->create();
        $parent = LessonComment::factory()->for($otherLesson)->for($student)->create(['body' => 'Private to another lesson']);
        Sanctum::actingAs($student);

        $this->getJson($this->url($course, $lesson))->assertOk()->assertJsonCount(0, 'data');
        $this->postJson($this->url($course, $lesson), ['body' => 'Wrong lesson', 'parent_id' => $parent->id])
            ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        $this->getJson($this->url($course, $otherCourseLesson))->assertNotFound();

        $this->assertDatabaseCount('lesson_comments', 1);
    }

    public function test_instructor_can_reply_only_on_their_own_course_with_permission(): void
    {
        [$student, $course, $lesson] = $this->accessibleLesson();
        $this->seed(RolesAndPermissionsSeeder::class);
        $instructor = User::factory()->create();
        $instructor->assignRole(RoleName::Instructor->value);
        $course->update(['instructor_id' => $instructor->id]);
        $parent = LessonComment::factory()->for($lesson)->for($student)->create();
        Sanctum::actingAs($instructor);

        $this->postJson($this->url($course, $lesson), ['body' => 'Instructor answer', 'parent_id' => $parent->id])
            ->assertCreated()->assertJsonPath('data.author.id', $instructor->id);
        $foreign = Lesson::factory()->published()->create();
        $this->getJson($this->url($foreign->section->course, $foreign))->assertForbidden();

        $role = Role::query()->where('name', RoleName::Instructor->value)->firstOrFail();
        $role->revokePermissionTo(PermissionName::CoursesView->value);
        $this->postJson($this->url($course, $lesson), ['body' => 'No permission'])->assertForbidden();
        $role->givePermissionTo(PermissionName::CoursesView->value);
        $role->revokePermissionTo(PermissionName::CurriculumView->value);
        $this->getJson($this->url($course, $lesson))->assertForbidden();
    }

    public function test_disabled_unpublished_or_inactive_discussions_are_not_available(): void
    {
        [$student, $course, $lesson] = $this->accessibleLesson();
        Sanctum::actingAs($student);
        $course->update(['discussion_enabled' => false]);

        $this->getJson($this->url($course, $lesson))->assertForbidden();
        $this->postJson($this->url($course, $lesson), ['body' => 'Hidden'])->assertForbidden();

        $course->update(['discussion_enabled' => true]);
        $lesson->update(['is_published' => false]);
        $this->getJson($this->url($course, $lesson))->assertNotFound();
        $lesson->update(['is_published' => true]);
        $lesson->section->update(['is_active' => false]);
        $this->getJson($this->url($course, $lesson))->assertNotFound();

        $this->assertDatabaseCount('lesson_comments', 0);
    }

    public function test_unauthorized_and_suspended_students_cannot_read_or_write_comments(): void
    {
        [$student, $course, $lesson] = $this->accessibleLesson();
        $this->getJson($this->url($course, $lesson))->assertUnauthorized();
        $this->postJson($this->url($course, $lesson), ['body' => 'Anonymous'])->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson($this->url($course, $lesson))->assertForbidden();

        $student->enrollments()->firstOrFail()->update(['status' => 'suspended']);
        Sanctum::actingAs($student);
        $this->postJson($this->url($course, $lesson), ['body' => 'Suspended'])->assertForbidden();
        $this->assertDatabaseCount('lesson_comments', 0);
    }

    public function test_invalid_body_and_unexpected_user_id_are_not_persisted(): void
    {
        [$student, $course, $lesson] = $this->accessibleLesson();
        Sanctum::actingAs($student);
        $this->postJson($this->url($course, $lesson), ['body' => ''])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->postJson($this->url($course, $lesson), ['body' => '   '])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->postJson($this->url($course, $lesson), ['body' => str_repeat('x', 2001)])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->postJson($this->url($course, $lesson), ['body' => 'Valid', 'user_id' => 999])
            ->assertCreated()->assertJsonPath('data.author.id', $student->id);

        $this->assertDatabaseHas('lesson_comments', ['body' => 'Valid', 'user_id' => $student->id]);
        $this->assertDatabaseCount('lesson_comments', 1);
    }

    public function test_comment_history_is_bounded_and_can_load_older_messages(): void
    {
        [$student, $course, $lesson] = $this->accessibleLesson();
        LessonComment::factory()->count(22)->for($lesson)->for($student)->create();
        Sanctum::actingAs($student);

        $first = $this->getJson($this->url($course, $lesson))->assertOk()->assertJsonCount(10, 'data');
        $before = $first->json('next_before');
        $second = $this->getJson($this->url($course, $lesson)."?before={$before}")->assertOk()->assertJsonCount(10, 'data');
        $this->getJson($this->url($course, $lesson).'?before='.$second->json('next_before'))
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('next_before', null);
    }

    public function test_replies_are_paginated_within_their_parent_thread(): void
    {
        [$student, $course, $lesson] = $this->accessibleLesson();
        $root = LessonComment::factory()->for($lesson)->for($student)->create();
        LessonComment::factory()->count(15)->for($lesson)->for($student)->create(['parent_id' => $root->id]);
        Sanctum::actingAs($student);

        $thread = $this->getJson($this->url($course, $lesson))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonCount(3, 'data.0.replies')
            ->assertJsonPath('data.0.replies_count', 15);
        $after = $thread->json('data.0.next_reply_after');
        $more = $this->getJson($this->repliesUrl($course, $lesson, $root)."?after={$after}")
            ->assertOk()->assertJsonCount(10, 'data');
        $this->getJson($this->repliesUrl($course, $lesson, $root).'?after='.$more->json('next_after'))
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('next_after', null);
    }

    public function test_reply_to_a_reply_is_grouped_under_the_original_comment(): void
    {
        [$student, $course, $lesson] = $this->accessibleLesson();
        $root = LessonComment::factory()->for($lesson)->for($student)->create();
        $reply = LessonComment::factory()->for($lesson)->for($student)->create(['parent_id' => $root->id]);
        Sanctum::actingAs($student);

        $this->postJson($this->url($course, $lesson), ['body' => 'Follow-up answer', 'parent_id' => $reply->id])
            ->assertCreated()->assertJsonPath('data.parent_id', $root->id);

        $this->assertDatabaseHas('lesson_comments', ['body' => 'Follow-up answer', 'parent_id' => $root->id]);
    }

    public function test_replies_for_a_comment_in_another_lesson_return_404(): void
    {
        [$student, $course, $lesson] = $this->accessibleLesson();
        $otherLesson = Lesson::factory()->published()->for($lesson->section, 'section')->create();
        $foreignRoot = LessonComment::factory()->for($otherLesson)->for($student)->create();
        Sanctum::actingAs($student);

        $this->getJson($this->repliesUrl($course, $lesson, $foreignRoot))->assertNotFound();
    }

    public function test_comments_remain_readable_after_the_author_is_soft_deleted(): void
    {
        [$student, $course, $lesson] = $this->accessibleLesson();
        $author = User::factory()->create(['name' => 'Previous learner']);
        LessonComment::factory()->for($lesson)->for($author)->create();
        $author->delete();
        Sanctum::actingAs($student);

        $this->getJson($this->url($course, $lesson))->assertOk()
            ->assertJsonPath('data.0.author.name', 'Previous learner');
    }

    /** @return array{User, Course, Lesson} */
    private function accessibleLesson(): array
    {
        $student = User::factory()->create();
        $course = Course::factory()->published()->create(['discussion_enabled' => true]);
        $section = CourseSection::factory()->for($course)->create();
        $lesson = Lesson::factory()->published()->for($section, 'section')->create();
        $this->enroll($student, $course);

        return [$student, $course, $lesson];
    }

    private function enroll(User $student, Course $course): void
    {
        $enrollment = Enrollment::factory()->for($student, 'user')->for($course)->create();
        EnrollmentAccessGrant::factory()->for($enrollment)->lifetime()->create();
    }

    private function url(Course $course, Lesson $lesson): string
    {
        return "/api/v1/me/courses/{$course->slug}/lessons/{$lesson->id}/comments";
    }

    private function repliesUrl(Course $course, Lesson $lesson, LessonComment $comment): string
    {
        return $this->url($course, $lesson)."/{$comment->id}/replies";
    }
}
