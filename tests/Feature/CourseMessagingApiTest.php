<?php

namespace Tests\Feature;

use App\EnrollmentStatus;
use App\Models\Course;
use App\Models\CourseConversation;
use App\Models\CourseMessage;
use App\Models\CourseMessageAttachment;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\User;
use App\UserStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CourseMessagingApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array{Course, User, User} */
    private function course(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $instructor = User::factory()->create();
        $instructor->assignRole('instructor');
        $instructor->givePermissionTo(['messaging.view', 'messaging.send', 'messaging.groups.manage']);
        $student = $this->student();
        $course = Course::factory()->for($instructor, 'instructor')->published()->create();
        $this->enroll($student, $course);

        return [$course, $instructor, $student];
    }

    private function student(): User
    {
        $student = User::factory()->create();
        $student->assignRole('student');
        $student->givePermissionTo(['messaging.view', 'messaging.send']);

        return $student;
    }

    private function enroll(User $student, Course $course): Enrollment
    {
        $enrollment = Enrollment::factory()->for($student)->for($course)->create();
        EnrollmentAccessGrant::factory()->for($enrollment)->lifetime()->create();

        return $enrollment;
    }

    private function privateChat(Course $course, User $student): int
    {
        Sanctum::actingAs($student);

        return $this->postJson("/api/v1/messaging/courses/{$course->id}/private")->assertOk()->json('data.id');
    }

    private function postMessage(int $conversation, string $body = 'مرحبا 😊'): int
    {
        return $this->postJson("/api/v1/messaging/conversations/{$conversation}/messages", ['body' => $body, 'client_id' => (string) Str::uuid()])->assertCreated()->json('data.id');
    }

    public function test_both_directions_return_the_same_canonical_private_chat_and_database_enforces_uniqueness(): void
    {
        [$course, $instructor, $student] = $this->course();
        $id = $this->privateChat($course, $student);
        Sanctum::actingAs($instructor);
        $this->postJson("/api/v1/messaging/courses/{$course->id}/private", ['student_id' => $student->id])->assertOk()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('course_conversations', 1);
        $this->expectException(QueryException::class);
        CourseConversation::query()->create(['course_id' => $course->id, 'instructor_id' => $instructor->id, 'student_id' => $student->id, 'kind' => 'private']);
    }

    public function test_instructor_can_initiate_before_student_and_student_cannot_choose_another_student(): void
    {
        [$course, $instructor, $student] = $this->course();
        Sanctum::actingAs($instructor);
        $id = $this->postJson("/api/v1/messaging/courses/{$course->id}/private", ['student_id' => $student->id])->assertOk()->json('data.id');
        $this->assertSame($id, $this->privateChat($course, $student));
        $other = $this->student();
        $this->enroll($other, $course);
        $this->postJson("/api/v1/messaging/courses/{$course->id}/private", ['student_id' => $other->id])->assertForbidden();
    }

    public function test_multiple_groups_selected_privacy_dynamic_future_enrollment_and_member_management(): void
    {
        [$course, $instructor, $student] = $this->course();
        Sanctum::actingAs($instructor);
        $selected = $this->postJson("/api/v1/messaging/courses/{$course->id}/groups", ['title' => 'الفريق الأول', 'kind' => 'selected', 'student_ids' => [$student->id]])->assertCreated()->json('data.id');
        $all = $this->postJson("/api/v1/messaging/courses/{$course->id}/groups", ['title' => 'الجميع', 'kind' => 'all'])->assertCreated()->json('data.id');
        $future = $this->student();
        $this->enroll($future, $course);
        Sanctum::actingAs($future);
        $this->getJson("/api/v1/messaging/conversations/{$selected}")->assertNotFound();
        $this->getJson("/api/v1/messaging/conversations/{$all}")->assertOk();
        $this->postMessage($all);
        $this->putJson("/api/v1/messaging/conversations/{$all}/members", ['student_ids' => [$future->id]])->assertForbidden();
        Sanctum::actingAs($instructor);
        $this->putJson("/api/v1/messaging/conversations/{$selected}/members", ['student_ids' => [$future->id]])->assertOk();
        Sanctum::actingAs($student);
        $this->getJson("/api/v1/messaging/conversations/{$selected}/messages")->assertNotFound();
        $this->assertDatabaseHas('course_conversation_members', ['course_conversation_id' => $selected, 'user_id' => $student->id]);
        Sanctum::actingAs($future);
        $this->getJson("/api/v1/messaging/conversations/{$selected}")->assertOk();
    }

    public static function inactiveAccess(): array
    {
        return [['expired'], ['revoked'], ['suspended'], ['future']];
    }

    #[DataProvider('inactiveAccess')]
    public function test_inactive_access_denies_messages_files_reads_and_channel_admission(string $state): void
    {
        [$course, $instructor, $student] = $this->course();
        $id = $this->privateChat($course, $student);
        Storage::fake('course_messaging');
        $message = $this->post("/api/v1/messaging/conversations/{$id}/messages", ['client_id' => (string) Str::uuid(), 'attachment' => UploadedFile::fake()->image('photo.png')], ['Accept' => 'application/json'])->assertCreated();
        $fileId = $message->json('data.attachments.0.id');
        $enrollment = Enrollment::query()->where('user_id', $student->id)->firstOrFail();
        if ($state === 'suspended') {
            $enrollment->update(['status' => EnrollmentStatus::Suspended]);
        }
        if ($state === 'expired') {
            $enrollment->accessGrants()->update(['access_expires_at' => now()->subSecond()]);
        }
        if ($state === 'revoked') {
            $enrollment->accessGrants()->update(['revoked_at' => now()]);
        }
        if ($state === 'future') {
            $enrollment->accessGrants()->update(['access_starts_at' => now()->addDay()]);
        }
        $this->getJson("/api/v1/messaging/conversations/{$id}/messages")->assertNotFound();
        $this->postJson("/api/v1/messaging/conversations/{$id}/read", ['message_id' => $message->json('data.id')])->assertNotFound();
        $this->getJson("/api/v1/messaging/attachments/{$fileId}")->assertNotFound();
        $this->getJson('/api/v1/messaging/notifications')->assertOk()->assertJsonPath('unread_count', 0);
        $callback = Broadcast::getChannels()['course-conversation.{conversation}'];
        $this->assertFalse($callback($student, $id));
    }

    public function test_instructor_reassignment_seals_history_for_old_and_new_instructors_and_student(): void
    {
        [$course, $instructor, $student] = $this->course();
        $old = $this->privateChat($course, $student);
        $this->postMessage($old, 'Private history');
        $new = User::factory()->create();
        $new->assignRole('instructor');
        $new->givePermissionTo(['messaging.view', 'messaging.send']);
        $course->update(['instructor_id' => $new->id]);
        foreach ([$student, $instructor, $new] as $viewer) {
            Sanctum::actingAs($viewer);
            $this->getJson("/api/v1/messaging/conversations/{$old}")->assertNotFound();
        }
        $next = $this->privateChat($course, $student);
        $this->assertNotSame($old, $next);
        $this->assertDatabaseHas('course_messages', ['course_conversation_id' => $old, 'body' => 'Private history']);
    }

    public function test_cross_course_message_reply_and_attachment_idor_are_denied(): void
    {
        [$course, $instructor, $student] = $this->course();
        $id = $this->privateChat($course, $student);
        $message = $this->postMessage($id);
        $otherCourse = Course::factory()->for($instructor, 'instructor')->create();
        $this->enroll($student, $otherCourse);
        $otherId = $this->privateChat($otherCourse, $student);
        $this->postJson("/api/v1/messaging/conversations/{$otherId}/messages", ['client_id' => (string) Str::uuid(), 'body' => 'Reply', 'reply_to_id' => $message])->assertUnprocessable()->assertJsonValidationErrors('reply_to_id');
        $outsider = $this->student();
        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/messaging/messages/{$message}")->assertNotFound();
        $this->postJson("/api/v1/messaging/messages/{$message}/reactions", ['emoji' => '👍'])->assertNotFound();
        $this->postJson("/api/v1/messaging/courses/{$course->id}/private")->assertForbidden();
    }

    public function test_idempotent_send_reactions_reply_tombstone_and_authorized_search(): void
    {
        [$course, $instructor, $student] = $this->course();
        $id = $this->privateChat($course, $student);
        $uuid = (string) Str::uuid();
        $payload = ['client_id' => $uuid, 'body' => '<script>alert(1)</script> مرحبا 😊', 'user_id' => $instructor->id];
        $message = $this->postJson("/api/v1/messaging/conversations/{$id}/messages", $payload)->assertCreated()->json('data.id');
        $this->postJson("/api/v1/messaging/conversations/{$id}/messages", $payload)->assertCreated()->assertJsonPath('data.id', $message);
        $this->assertDatabaseCount('course_messages', 1);
        $this->assertDatabaseHas('course_messages', ['id' => $message, 'user_id' => $student->id]);
        $this->postJson("/api/v1/messaging/messages/{$message}/reactions", ['emoji' => '👍'])->assertOk();
        $this->getJson("/api/v1/messaging/messages/{$message}")->assertOk()->assertJsonPath('data.reactions.0.count', 1);
        $this->postJson("/api/v1/messaging/messages/{$message}/reactions", ['emoji' => '👍'])->assertOk();
        $this->assertDatabaseCount('course_message_reactions', 0);
        $reply = $this->postJson("/api/v1/messaging/conversations/{$id}/messages", ['client_id' => (string) Str::uuid(), 'body' => 'Reply', 'reply_to_id' => $message])->assertCreated()->json('data.id');
        $this->getJson("/api/v1/messaging/conversations/{$id}/messages?search=مرحبا")->assertOk()->assertJsonCount(1, 'data');
        Sanctum::actingAs($instructor);
        $this->deleteJson("/api/v1/messaging/messages/{$message}")->assertForbidden();
        Sanctum::actingAs($student);
        $this->deleteJson("/api/v1/messaging/messages/{$message}")->assertOk();
        $this->getJson("/api/v1/messaging/messages/{$reply}")->assertOk()->assertJsonPath('data.reply.deleted', true)->assertJsonPath('data.reply.body', null);
        $this->getJson("/api/v1/messaging/messages/{$message}")->assertOk()->assertJsonPath('data.body', null)->assertJsonPath('data.deleted', true);
        $this->postJson("/api/v1/messaging/messages/{$message}/reactions", ['emoji' => '👍'])->assertUnprocessable();
    }

    public function test_monotonic_read_receipts_unread_notifications_no_self_notification_and_deleted_counts(): void
    {
        [$course, $instructor, $student] = $this->course();
        $id = $this->privateChat($course, $student);
        $first = $this->postMessage($id);
        $last = $this->postMessage($id);
        $this->getJson('/api/v1/messaging/notifications')->assertJsonPath('unread_count', 0);
        Sanctum::actingAs($instructor);
        $this->getJson('/api/v1/messaging/notifications')->assertJsonPath('unread_count', 2)->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/messaging/conversations/{$id}/read", ['message_id' => $last])->assertOk();
        $this->postJson("/api/v1/messaging/conversations/{$id}/read", ['message_id' => $first])->assertOk();
        $this->assertDatabaseHas('course_read_cursors', ['course_conversation_id' => $id, 'user_id' => $instructor->id, 'last_read_message_id' => $last]);
        $this->getJson('/api/v1/messaging/notifications')->assertJsonPath('unread_count', 0);
        $this->getJson("/api/v1/messaging/messages/{$last}")->assertJsonPath('data.reader_count', 1);
        Sanctum::actingAs($student);
        $new = $this->postMessage($id);
        $this->deleteJson("/api/v1/messaging/messages/{$new}")->assertOk();
        Sanctum::actingAs($instructor);
        $this->getJson('/api/v1/messaging/notifications')->assertJsonPath('unread_count', 0);
    }

    public function test_private_images_authorized_download_and_deletion_disables_download(): void
    {
        [$course, $instructor, $student] = $this->course();
        $id = $this->privateChat($course, $student);
        Storage::fake('course_messaging');
        $result = $this->post("/api/v1/messaging/conversations/{$id}/messages", ['client_id' => (string) Str::uuid(), 'attachment' => UploadedFile::fake()->image('صورة.png')], ['Accept' => 'application/json'])->assertCreated();
        $file = $result->json('data.attachments.0.id');
        $message = $result->json('data.id');
        $this->getJson("/api/v1/messaging/attachments/{$file}")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $outsider = $this->student();
        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/messaging/attachments/{$file}")->assertNotFound();
        Sanctum::actingAs($student);
        $this->deleteJson("/api/v1/messaging/messages/{$message}")->assertOk();
        $this->getJson("/api/v1/messaging/attachments/{$file}")->assertNotFound();
        $this->assertDatabaseCount('course_message_attachments', 1);
    }

    public function test_reply_metadata_includes_protected_image_or_voice_preview_and_hides_deleted_media(): void
    {
        [$course, , $student] = $this->course();
        $conversation = $this->privateChat($course, $student);
        Storage::fake('course_messaging');
        $image = $this->post("/api/v1/messaging/conversations/{$conversation}/messages", [
            'client_id' => (string) Str::uuid(),
            'attachment' => UploadedFile::fake()->image('reply.png'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $imageMessageId = $image->json('data.id');
        $imageFileId = $image->json('data.attachments.0.id');

        $imageReply = $this->postJson("/api/v1/messaging/conversations/{$conversation}/messages", [
            'client_id' => (string) Str::uuid(), 'body' => 'Seen', 'reply_to_id' => $imageMessageId,
        ])->assertCreated();
        $imageReply->assertJsonPath('data.reply.attachment.kind', 'image')
            ->assertJsonPath('data.reply.attachment.id', $imageFileId)
            ->assertJsonPath('data.reply.attachment.url', route('messaging.attachments.show', $imageFileId));

        $voiceMessageId = $this->postMessage($conversation, 'Voice note');
        $voice = CourseMessageAttachment::factory()->create([
            'course_message_id' => $voiceMessageId, 'kind' => 'voice', 'mime_type' => 'audio/webm', 'original_filename' => 'note.webm',
        ]);
        $voiceReplyId = $this->postJson("/api/v1/messaging/conversations/{$conversation}/messages", [
            'client_id' => (string) Str::uuid(), 'body' => 'Heard', 'reply_to_id' => $voiceMessageId,
        ])->assertCreated()->json('data.id');
        $this->getJson("/api/v1/messaging/messages/{$voiceReplyId}")
            ->assertJsonPath('data.reply.attachment.kind', 'voice')
            ->assertJsonPath('data.reply.attachment.id', $voice->id);

        $this->deleteJson("/api/v1/messaging/messages/{$imageMessageId}")->assertOk();
        $this->getJson("/api/v1/messaging/messages/{$imageReply->json('data.id')}")
            ->assertJsonPath('data.reply.deleted', true)
            ->assertJsonPath('data.reply.attachment', null);
    }

    public function test_actual_voice_file_is_probed_and_fake_or_oversized_media_is_rejected(): void
    {
        [$course, $instructor, $student] = $this->course();
        $id = $this->privateChat($course, $student);
        Storage::fake('course_messaging');
        $sampleRate = 8000;
        $samples = str_repeat("\0", $sampleRate * 2);
        $wav = 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, $sampleRate, $sampleRate * 2, 2, 16).'data'.pack('V', strlen($samples)).$samples;
        $this->post("/api/v1/messaging/conversations/{$id}/messages", ['client_id' => (string) Str::uuid(), 'attachment' => UploadedFile::fake()->createWithContent('voice.wav', $wav)], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.attachments.0.kind', 'voice')->assertJsonPath('data.attachments.0.duration', 1);
        $this->post("/api/v1/messaging/conversations/{$id}/messages", ['client_id' => (string) Str::uuid(), 'attachment' => UploadedFile::fake()->createWithContent('voice.wav', '<script>bad</script>')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post("/api/v1/messaging/conversations/{$id}/messages", ['client_id' => (string) Str::uuid(), 'attachment' => UploadedFile::fake()->image('big.jpg')->size(6000)], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_portable_voice_upload_works_without_ffprobe_but_does_not_claim_a_verified_duration(): void
    {
        [$course, $instructor, $student] = $this->course();
        $id = $this->privateChat($course, $student);
        Storage::fake('course_messaging');
        $samples = str_repeat("\0", 16000);
        $wav = 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', strlen($samples)).$samples;
        config()->set('messaging.ffprobe', 'C:/missing/ffprobe.exe');
        config()->set('messaging.voice_validation', 'portable');

        $this->post("/api/v1/messaging/conversations/{$id}/messages", ['client_id' => (string) Str::uuid(), 'attachment' => UploadedFile::fake()->createWithContent('voice.wav', $wav)], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.attachments.0.kind', 'voice')->assertJsonPath('data.attachments.0.duration', null);
        $this->assertDatabaseHas('course_message_attachments', ['kind' => 'voice', 'duration_seconds' => null]);

        config()->set('messaging.voice_validation', 'strict');

        $this->post("/api/v1/messaging/conversations/{$id}/messages", ['client_id' => (string) Str::uuid(), 'attachment' => UploadedFile::fake()->createWithContent('voice.wav', $wav)], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('attachment');
    }

    public function test_portable_voice_upload_accepts_audio_only_webm_recording_without_ffprobe(): void
    {
        [$course, $instructor, $student] = $this->course();
        $id = $this->privateChat($course, $student);
        Storage::fake('course_messaging');
        config()->set('messaging.ffprobe', 'C:/missing/ffprobe.exe');
        config()->set('messaging.voice_validation', 'portable');
        $webm = base64_decode(<<<'WEBM'
GkXfowEAAAAAAAAfQoaBAUL3gQFC8oEEQvOBCEKChHdlYm1Ch4EEQoWBAhhTgGcB/////////+wB
AAAAAAAA3AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA
AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA
AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA
AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAV
SalmAQAAAAAAACcq17GDD0JATYCNTGF2ZjU4LjI0LjEwMVdBjUxhdmY1OC4yNC4xMDEWVK5rAQAA
AAAAAGKuAQAAAAAAAFnXgQFzxYEBnIEAIrWcg3VuZIaGQV9PUFVTVqqDEIfFVruEBMS0AIOBAuEB
AAAAAAAAEZ+BAbWIQL9AAAAAAABiZIEQY6KTT3B1c0hlYWQBATQAQB8AAAAAABJUw2cBAAAAAAAA
fHNzAQAAAAAAAC5jwAEAAAAAAAAAZ8gBAAAAAAAAGkWjh0VOQ09ERVJEh41MYXZmNTguMjQuMTAx
c3MBAAAAAAAAOmPAAQAAAAAAAARjxYEBZ8gBAAAAAAAAIkWjh0VOQ09ERVJEh5VMYXZjNTguNDIu
MTAyIGxpYm9wdXMfQ7Z1AQAAAAAAAUzngQCji4EAAIAIC+Y7I6tgo4qBABWACAissw7Go4qBACmA
CAissw7Go4qBAD2ACAissw7Go4qBAFGACAissw7Go4qBAGWACAissw7Go4qBAHmACAissw7Go4qB
AI2ACAissw7Go4qBAKGACAissw7Go4qBALWACAissw7Go4qBAMmACAissw7Go4qBAN2ACAissw7G
o4qBAPGACAissw7Go4qBAQWACAissw7Go4qBARmACAissw7Go4qBAS2ACAissw7Go4qBAUGACAis
sw7Go4qBAVWACAissw7Go4qBAWmACAissw7Go4qBAZGACAissw7Go4qBAaWA
CAissw7Go4qBAbmACAissw7Go4qBAc2ACAissw7Go4qBAeGACAissw7GoAEAAAAAAAAToYqBAfUA
CAissw7GdaKEAM3+YA==
WEBM, true);

        $this->post("/api/v1/messaging/conversations/{$id}/messages", ['client_id' => (string) Str::uuid(), 'attachment' => UploadedFile::fake()->createWithContent('voice.webm', $webm)], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.attachments.0.kind', 'voice')->assertJsonPath('data.attachments.0.duration', null);
    }

    public function test_effective_permissions_revocation_inactive_users_and_no_admin_private_visibility(): void
    {
        [$course, $instructor, $student] = $this->course();
        $id = $this->privateChat($course, $student);
        $student->revokePermissionTo('messaging.send');
        $this->postJson("/api/v1/messaging/conversations/{$id}/messages", ['client_id' => (string) Str::uuid(), 'body' => 'No'])->assertForbidden();
        Sanctum::actingAs($instructor);
        $instructor->revokePermissionTo('messaging.groups.manage');
        $this->postJson("/api/v1/messaging/courses/{$course->id}/groups", ['kind' => 'all', 'title' => 'No'])->assertForbidden();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->givePermissionTo('messaging.view');
        Sanctum::actingAs($admin);
        $this->getJson("/api/v1/messaging/conversations/{$id}")->assertNotFound();
        Sanctum::actingAs($student);
        $student->update(['status' => UserStatus::Blocked]);
        $this->getJson('/api/v1/messaging/conversations')->assertForbidden();
    }

    public function test_channel_authorization_and_durable_reconnect_event_versions(): void
    {
        [$course, $instructor, $student] = $this->course();
        $id = $this->privateChat($course, $student);
        $callback = Broadcast::getChannels()['course-conversation.{conversation}'];
        $this->assertTrue($callback($student, $id));
        $this->assertTrue($callback($instructor, $id));
        $this->assertFalse($callback($this->student(), $id));
        $message = $this->postMessage($id);
        $this->postJson("/api/v1/messaging/messages/{$message}/reactions", ['emoji' => '❤️'])->assertOk();
        $this->getJson("/api/v1/messaging/conversations/{$id}/events?after=0")->assertOk()->assertJsonPath('data.0.version', 1)->assertJsonPath('data.1.version', 2)->assertJsonPath('version', 2);
        $this->getJson("/api/v1/messaging/conversations/{$id}/events?after=1")->assertJsonCount(1, 'data');
    }

    public function test_authentication_empty_messages_invalid_reactions_and_rate_limit(): void
    {
        $this->getJson('/api/v1/messaging/conversations')->assertUnauthorized();
        [$course, $instructor, $student] = $this->course();
        $id = $this->privateChat($course, $student);
        $this->postJson("/api/v1/messaging/conversations/{$id}/messages", ['client_id' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('body');
        $message = $this->postMessage($id);
        $this->postJson("/api/v1/messaging/messages/{$message}/reactions", ['emoji' => '<script>'])->assertUnprocessable();
        for ($i = 0; $i < 28; $i++) {
            $this->postMessage($id);
        }
        $this->postJson("/api/v1/messaging/conversations/{$id}/messages", ['client_id' => (string) Str::uuid(), 'body' => 'Flood'])->assertTooManyRequests();
    }

    public function test_seeding_never_auto_grants_messaging_or_restores_revoked_permissions(): void
    {
        [$course, $instructor, $student] = $this->course();
        $student->revokePermissionTo('messaging.send');
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->assertFalse($student->fresh()->can('messaging.send'));
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->assertFalse($admin->can('messaging.view'));
    }

    public function test_http_channel_auth_rejects_foreign_and_unknown_channels_even_with_null_broadcaster(): void
    {
        [$course, $instructor, $student] = $this->course();
        $id = $this->privateChat($course, $student);
        $this->postJson('/api/v1/broadcasting/auth', ['channel_name' => 'private-course-conversation.'.$id, 'socket_id' => '1.1'])->assertOk();
        $this->postJson('/api/v1/broadcasting/auth', ['channel_name' => 'private-unrelated.1', 'socket_id' => '1.1'])->assertForbidden();
        $outsider = $this->student();
        Sanctum::actingAs($outsider);
        $this->postJson('/api/v1/broadcasting/auth', ['channel_name' => 'private-course-conversation.'.$id, 'socket_id' => '1.1'])->assertForbidden();
    }

    public function test_paginated_history_search_and_read_cursor_cannot_cross_conversations(): void
    {
        [$course, $instructor, $student] = $this->course();
        $id = $this->privateChat($course, $student);
        CourseMessage::factory()->count(55)->create(['course_conversation_id' => $id, 'user_id' => $instructor->id]);
        $this->getJson('/api/v1/messaging/conversations/'.$id.'/messages')->assertOk()->assertJsonCount(50, 'data');
        $before = CourseMessage::query()->where('course_conversation_id', $id)->orderByDesc('id')->skip(49)->value('id');
        $this->getJson('/api/v1/messaging/conversations/'.$id.'/messages?before='.$before)->assertOk()->assertJsonCount(5, 'data');
        $foreign = CourseMessage::factory()->create();
        $this->postJson('/api/v1/messaging/conversations/'.$id.'/read', ['message_id' => $foreign->id])->assertUnprocessable()->assertJsonValidationErrors('message_id');
        $this->getJson('/api/v1/messaging/conversations?unread=1')->assertOk()->assertJsonCount(1, 'data');
    }
}
