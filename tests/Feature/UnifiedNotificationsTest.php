<?php

namespace Tests\Feature;

use App\Events\PlatformNotificationEvent;
use App\Jobs\SendPlatformNotification;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseConversation;
use App\Models\CourseMessage;
use App\Models\CourseReadCursor;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\Lesson;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\NotificationReceipt;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PlatformNotification;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\TransactionalDelivery;
use App\Models\User;
use App\Notifications\PlatformEventNotification;
use App\Services\AnnouncementService;
use App\Services\NotificationTargetService;
use App\Services\NotificationTypeRegistry;
use App\Services\UnifiedNotificationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnifiedNotificationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function user(string $role = 'student'): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    private function course(User $student, ?User $instructor = null, ?string $expiry = null): Course
    {
        $instructor ??= $this->user('instructor');
        $course = Course::factory()->for($instructor, 'instructor')->published()->create();
        $e = Enrollment::factory()->for($student)->for($course)->create();
        EnrollmentAccessGrant::factory()->for($e)->create(['access_starts_at' => now()->subDay(), 'access_expires_at' => $expiry]);

        return $course;
    }

    private function emit(User $user, string $type = 'security.changed', string $key = 'event1'): void
    {
        app(UnifiedNotificationService::class)->deliver($user, new PlatformNotificationEvent($type, $user, $key));
    }

    public function test_inbox_is_persisted_idempotent_localized_and_isolated(): void
    {
        Bus::fake([SendPlatformNotification::class]);
        $a = $this->user();
        $b = $this->user();
        $this->emit($a);
        $this->emit($a);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('notification_receipts', 1);
        $this->assertDatabaseCount('notification_deliveries', 1);
        Sanctum::actingAs($a);
        $id = $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('meta.unread_count', 1)->json('data.0.id');
        Sanctum::actingAs($b);
        $this->postJson('/api/v1/notifications/'.$id.'/read')->assertNotFound();
        $this->postJson('/api/v1/notifications/'.$id.'/open')->assertNotFound();
        Sanctum::actingAs($a);
        $this->postJson('/api/v1/notifications/'.$id.'/read')->assertOk();
        $this->getJson('/api/v1/notifications?unread=1')->assertJsonCount(0, 'data')->assertJsonPath('meta.unread_count', 0);
    }

    public function test_rollback_discards_notification_and_outbox_atomically(): void
    {
        Bus::fake([SendPlatformNotification::class]);
        $u = $this->user();
        DB::beginTransaction();
        $this->emit($u);
        DB::rollBack();
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('notification_deliveries', 0);
    }

    public function test_preferences_cannot_disable_mandatory_security_and_preserve_optional_preferences(): void
    {
        $u = $this->user();
        Sanctum::actingAs($u);
        $this->putJson('/api/v1/notification-preferences', ['preferences' => [['type' => 'security.changed', 'in_app' => false, 'email' => false, 'digest' => true], ['type' => 'message.received', 'in_app' => false, 'email' => false, 'digest' => false]]])->assertOk();
        $this->assertDatabaseHas('notification_preferences', ['user_id' => $u->id, 'type' => 'security.changed', 'in_app' => true, 'email' => true, 'digest' => false]);
        $this->assertSame(false, app(NotificationTypeRegistry::class)->channels($u, 'message.received')['in_app']);
        $this->assertSame(true, app(NotificationTypeRegistry::class)->channels($u, 'lesson.published')['in_app']);
    }

    public function test_message_recipients_are_scoped_aggregated_and_cursors_clear_only_matching_conversations(): void
    {
        Bus::fake([SendPlatformNotification::class]);
        $s = $this->user();
        $i = $this->user('instructor');
        $admin = $this->user('admin');
        $s->givePermissionTo(['messaging.view', 'messaging.send']);
        $i->givePermissionTo(['messaging.view', 'messaging.send']);
        $c = $this->course($s, $i);
        PlatformNotification::query()->delete();
        NotificationReceipt::query()->delete();
        $conversation = CourseConversation::query()->create(['course_id' => $c->id, 'instructor_id' => $i->id, 'student_id' => $s->id, 'kind' => 'private']);
        $first = CourseMessage::query()->create(['course_conversation_id' => $conversation->id, 'user_id' => $i->id, 'client_id' => 'x1', 'body' => 'TOP SECRET']);
        $second = CourseMessage::query()->create(['course_conversation_id' => $conversation->id, 'user_id' => $i->id, 'client_id' => 'x2', 'body' => 'TOP SECRET 2']);
        $this->assertDatabaseCount('notifications', 1);
        $n = PlatformNotification::query()->first();
        $this->assertSame($s->id, $n->notifiable_id);
        $this->assertSame(2, $n->aggregate_count);
        $this->assertStringNotContainsString('TOP SECRET', json_encode($n->data));
        CourseReadCursor::query()->create(['course_conversation_id' => $conversation->id, 'user_id' => $s->id, 'last_read_message_id' => $first->id]);
        $this->assertNull($n->fresh()->read_at);
        CourseReadCursor::query()->where('user_id', $s->id)->first()->update(['last_read_message_id' => $second->id]);
        $this->assertNotNull($n->fresh()->read_at);
        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/notifications/'.$n->id.'/open')->assertNotFound();
    }

    public function test_active_conversation_presence_suppresses_duplicate_notification_and_revocation_blocks_links(): void
    {
        $s = $this->user();
        $i = $this->user('instructor');
        $s->givePermissionTo(['messaging.view', 'messaging.send']);
        $i->givePermissionTo(['messaging.view', 'messaging.send']);
        $c = $this->course($s, $i);
        $conversation = CourseConversation::query()->create(['course_id' => $c->id, 'instructor_id' => $i->id, 'student_id' => $s->id, 'kind' => 'private']);
        Sanctum::actingAs($s);
        $this->putJson('/api/v1/notification-presence', ['conversation_id' => $conversation->id])->assertOk();
        CourseMessage::query()->create(['course_conversation_id' => $conversation->id, 'user_id' => $i->id, 'client_id' => 'x1', 'body' => 'hello']);
        $this->assertDatabaseMissing('notifications', ['type' => 'message.received']);
        $this->deleteJson('/api/v1/notification-presence', ['conversation_id' => $conversation->id])->assertOk();
        CourseMessage::query()->create(['course_conversation_id' => $conversation->id, 'user_id' => $i->id, 'client_id' => 'x2', 'body' => 'hello']);
        $n = PlatformNotification::query()->where('type', 'message.received')->firstOrFail();
        Enrollment::query()->where('user_id', $s->id)->first()->update(['status' => 'suspended']);
        $this->postJson('/api/v1/notifications/'.$n->id.'/open')->assertNotFound();
    }

    public function test_payment_review_notifies_permission_holders_once_even_with_multiple_roles(): void
    {
        $s = $this->user();
        $reviewer = $this->user('sales_support');
        $reviewer->assignRole('admin');
        $other = $this->user('instructor');
        $order = Order::factory()->for($s)->create();
        Payment::factory()->for($order)->create(['status' => 'pending_review']);
        $this->assertSame(1, PlatformNotification::query()->where('type', 'payment.pending')->where('notifiable_id', $reviewer->id)->count());
        $this->assertSame(0, PlatformNotification::query()->where('type', 'payment.pending')->where('notifiable_id', $other->id)->count());
    }

    public function test_staff_support_internal_note_never_notifies_customer_and_assignment_is_scoped(): void
    {
        Bus::fake([SendPlatformNotification::class]);
        $s = $this->user();
        $staff = $this->user('sales_support');
        $ticket = SupportTicket::factory()->for($s)->create(['assigned_to' => $staff->id]);
        PlatformNotification::query()->delete();
        SupportTicketMessage::factory()->create(['support_ticket_id' => $ticket->id, 'user_id' => $staff->id, 'is_internal' => true]);
        $this->assertDatabaseMissing('notifications', ['type' => 'support.reply', 'notifiable_id' => $s->id]);
        SupportTicketMessage::factory()->create(['support_ticket_id' => $ticket->id, 'user_id' => $staff->id, 'is_internal' => false]);
        $this->assertDatabaseHas('notifications', ['type' => 'support.reply', 'notifiable_id' => $s->id]);
    }

    public function test_announcements_snapshot_rechecks_effective_access_and_is_idempotent(): void
    {
        $admin = $this->user('admin');
        $s = $this->user();
        $c = $this->course($s);
        $a = Announcement::query()->create(['created_by' => $admin->id, 'title' => ['ar' => 'إعلان', 'en' => 'Announcement'], 'body' => ['ar' => 'نص', 'en' => 'Text'], 'audience' => ['kind' => 'course_students', 'course_ids' => [$c->id]], 'status' => 'draft']);
        $service = app(AnnouncementService::class);
        $service->publish($a, $admin);
        $service->publish($a, $admin);
        $this->assertDatabaseCount('announcement_recipients', 1);
        $this->assertSame(1, PlatformNotification::query()->where('type', 'announcement.published')->count());
        $new = $this->user();
        $this->assertFalse($service->canRead($new, $a->fresh()));
        Sanctum::actingAs($s);
        $this->getJson('/api/v1/announcements/'.$a->id)->assertOk()->assertJsonPath('data.title.ar', 'إعلان');
        $this->assertDatabaseHas('announcement_recipients', ['user_id' => $s->id, 'announcement_id' => $a->id]);
        Enrollment::query()->where('user_id', $s->id)->first()->update(['status' => 'suspended']);
        $this->getJson('/api/v1/announcements/'.$a->id)->assertNotFound();
    }

    public function test_announcement_publication_permissions_and_expiry_are_enforced(): void
    {
        $s = $this->user();
        Sanctum::actingAs($s);
        $this->getJson('/api/v1/admin/announcements')->assertForbidden();
        $admin = $this->user('admin');
        $a = Announcement::query()->create(['created_by' => $admin->id, 'title' => ['ar' => 'a', 'en' => 'a'], 'body' => ['ar' => 'a', 'en' => 'a'], 'audience' => ['kind' => 'all'], 'status' => 'draft', 'expires_at' => now()->subMinute()]);
        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/admin/announcements/'.$a->id.'/publish')->assertUnprocessable();
    }

    public function test_scheduled_reminders_and_publication_are_idempotent(): void
    {
        Bus::fake([SendPlatformNotification::class]);
        $admin = $this->user('admin');
        $s = $this->user();
        $this->course($s, null, now()->addDays(3)->toDateTimeString());
        $a = Announcement::query()->create(['created_by' => $admin->id, 'title' => ['ar' => 'a', 'en' => 'a'], 'body' => ['ar' => 'a', 'en' => 'a'], 'audience' => ['kind' => 'roles', 'roles' => ['student']], 'status' => 'scheduled', 'publish_at' => now()->subMinute()]);
        $this->artisan('notifications:maintain')->assertSuccessful();
        $this->artisan('notifications:maintain')->assertSuccessful();
        $this->assertSame(1, PlatformNotification::query()->where('type', 'enrollment.expiring')->count());
        $this->assertSame('published', $a->fresh()->status);
    }

    public function test_email_job_rechecks_preferences_and_cannot_send_revoked_course_message(): void
    {
        Notification::fake();
        $u = $this->user();
        $delivery = NotificationDelivery::query()->create(['user_id' => $u->id, 'event_key' => 'test', 'type' => 'message.received', 'payload' => ['event_type' => 'message.received', 'target_type' => 'conversation', 'target_id' => 999], 'available_at' => now()]);
        (new SendPlatformNotification($delivery->id))->handle(app(NotificationTargetService::class), app(NotificationTypeRegistry::class));
        $this->assertSame('cancelled', $delivery->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_email_job_tracks_success_and_deduplicates_redelivery(): void
    {
        Notification::fake();
        $u = $this->user();
        $delivery = NotificationDelivery::query()->create(['user_id' => $u->id, 'event_key' => 'test', 'type' => 'security.changed', 'payload' => ['event_type' => 'security.changed', 'target_type' => 'user', 'target_id' => $u->id], 'available_at' => now()]);
        $job = new SendPlatformNotification($delivery->id);
        $job->handle(app(NotificationTargetService::class), app(NotificationTypeRegistry::class));
        $job->handle(app(NotificationTargetService::class), app(NotificationTypeRegistry::class));
        $this->assertSame('sent', $delivery->fresh()->status);
        Notification::assertSentToTimes($u, PlatformEventNotification::class, 1);
    }

    public function test_admin_catalog_never_exposes_message_contents_and_retry_is_authorized(): void
    {
        Bus::fake([SendPlatformNotification::class]);
        $admin = $this->user('admin');
        $u = $this->user();
        $d = NotificationDelivery::query()->create(['user_id' => $u->id, 'event_key' => 'x', 'type' => 'security.changed', 'payload' => ['private' => 'SECRET'], 'status' => 'failed', 'available_at' => now()]);
        Sanctum::actingAs($u);
        $this->postJson('/api/v1/admin/notification-management/deliveries/'.$d->id.'/retry')->assertForbidden();
        Sanctum::actingAs($admin);
        $result = $this->getJson('/api/v1/admin/notification-management')->assertOk();
        $this->assertStringNotContainsString('SECRET', $result->getContent());
        $this->postJson('/api/v1/admin/notification-management/deliveries/'.$d->id.'/retry')->assertOk();
        Bus::assertDispatched(SendPlatformNotification::class);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'notification.delivery.retry']);
    }

    public function test_content_and_grading_recipients_use_course_and_permission_scope(): void
    {
        Bus::fake([SendPlatformNotification::class]);
        $student = $this->user();
        $assigned = $this->user('instructor');
        $other = $this->user('instructor');
        $manager = $this->user('content_manager');
        $course = $this->course($student, $assigned);
        $assignment = Assignment::factory()->for($course)->create(['status' => 'published']);
        $enrollment = Enrollment::query()->where('user_id', $student->id)->first();
        $submission = AssignmentSubmission::factory()->for($assignment)->for($student)->for($enrollment)->submitted()->create();
        $ids = PlatformNotification::query()->where('type', 'assignment.submitted')->pluck('notifiable_id')->all();
        $this->assertContains($assigned->id, $ids);
        $this->assertContains($manager->id, $ids);
        $this->assertNotContains($other->id, $ids);
        $course->update(['title' => 'Revised course']);
        $this->assertDatabaseHas('notifications', ['type' => 'course.updated', 'notifiable_id' => $manager->id]);
        $this->assertDatabaseMissing('notifications', ['type' => 'course.updated', 'notifiable_id' => $other->id]);
        $submission->update(['status' => 'graded', 'graded_at' => now(), 'score' => 70]);
        $this->assertDatabaseHas('notifications', ['type' => 'assignment.graded', 'notifiable_id' => $student->id]);
        Sanctum::actingAs($other);
        $id = PlatformNotification::query()->where('type', 'assignment.submitted')->where('notifiable_id', $assigned->id)->value('id');
        $this->postJson('/api/v1/notifications/'.$id.'/open')->assertNotFound();
    }

    public function test_deadline_reminders_exclude_finished_and_suspended_students(): void
    {
        Bus::fake([SendPlatformNotification::class]);
        $student = $this->user();
        $course = $this->course($student);
        $enrollment = Enrollment::query()->where('user_id', $student->id)->first();
        $quiz = Quiz::factory()->for($course)->create(['status' => 'published', 'available_until' => now()->addHours(12)]);
        $assignment = Assignment::factory()->for($course)->create(['status' => 'published', 'due_at' => now()->addHours(12)]);
        QuizAttempt::factory()->for($quiz)->for($student)->for($enrollment)->submitted()->create();
        $this->artisan('notifications:maintain')->assertSuccessful();
        $this->artisan('notifications:maintain')->assertSuccessful();
        $this->assertDatabaseMissing('notifications', ['type' => 'quiz.reminder', 'notifiable_id' => $student->id]);
        $this->assertSame(1, PlatformNotification::query()->where('type', 'assignment.reminder')->where('notifiable_id', $student->id)->count());
        $enrollment->update(['status' => 'suspended']);
        $assignment->update(['due_at' => now()->addHours(10)]);
        $this->artisan('notifications:maintain')->assertSuccessful();
        $this->assertSame(1, PlatformNotification::query()->where('type', 'assignment.reminder')->where('notifiable_id', $student->id)->count());
    }

    public function test_digest_is_idempotent_and_pruning_preserves_unread_notifications(): void
    {
        Bus::fake([SendPlatformNotification::class]);
        $user = $this->user();
        NotificationPreference::query()->create(['user_id' => $user->id, 'type' => 'support.reply', 'in_app' => true, 'email' => true, 'digest' => true]);
        $ticket = SupportTicket::factory()->for($user)->create();
        app(UnifiedNotificationService::class)->deliver($user, new PlatformNotificationEvent('support.reply', $ticket, 'digest-source'));
        $this->assertDatabaseHas('notification_deliveries', ['type' => 'support.reply', 'status' => 'digest_pending']);
        $this->artisan('notifications:maintain --digest')->assertSuccessful();
        $this->artisan('notifications:maintain --digest')->assertSuccessful();
        $this->assertSame(1, NotificationDelivery::query()->where('type', 'digest.daily')->count());
        $this->assertDatabaseHas('notification_deliveries', ['type' => 'support.reply', 'status' => 'digested']);
        $this->emit($user, 'security.changed', 'old-read');
        $this->emit($user, 'security.changed', 'old-unread');
        $rows = PlatformNotification::query()->where('type', 'security.changed')->get();
        DB::table('notifications')->where('id', $rows[0]->id)->update(['read_at' => now()->subYear(), 'updated_at' => now()->subYear()]);
        DB::table('notifications')->where('id', $rows[1]->id)->update(['updated_at' => now()->subYear()]);
        $this->artisan('notifications:maintain --prune')->assertSuccessful();
        $this->assertDatabaseMissing('notifications', ['id' => $rows[0]->id]);
        $this->assertDatabaseHas('notifications', ['id' => $rows[1]->id]);
    }

    public function test_failed_mail_is_tracked_without_recording_transport_secrets(): void
    {
        Bus::fake([SendPlatformNotification::class]);
        $user = $this->user();
        $this->emit($user);
        $delivery = NotificationDelivery::query()->where('type', 'security.changed')->firstOrFail();
        $sender = \Mockery::mock(Dispatcher::class);
        $sender->shouldReceive('sendNow')->once()->andThrow(new \RuntimeException('SMTP PRIVATE PASSWORD'));
        $this->app->instance(Dispatcher::class, $sender);
        $job = new SendPlatformNotification($delivery->id);
        try {
            $job->handle(app(NotificationTargetService::class), app(NotificationTypeRegistry::class));
            $this->fail('Expected mail failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('SMTP PRIVATE PASSWORD', $e->getMessage());
        }
        $this->assertSame('queued', $delivery->fresh()->status);
        $this->assertSame('RuntimeException', $delivery->fresh()->error_code);
        $job->failed(new \RuntimeException('SMTP PRIVATE PASSWORD'));
        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertStringNotContainsString('PRIVATE', json_encode($delivery->fresh()->toArray()));
        $this->assertSame([60, 300, 900, 1800], $job->backoff);
    }

    public function test_deleting_messages_updates_aggregate_unread_state(): void
    {
        $student = $this->user();
        $teacher = $this->user('instructor');
        $student->givePermissionTo('messaging.view');
        $teacher->givePermissionTo('messaging.view');
        $course = $this->course($student, $teacher);
        $conversation = CourseConversation::query()->create(['course_id' => $course->id, 'instructor_id' => $teacher->id, 'student_id' => $student->id, 'kind' => 'private']);
        $message = CourseMessage::query()->create(['course_conversation_id' => $conversation->id, 'user_id' => $teacher->id, 'client_id' => 'delete-message', 'body' => 'secret']);
        $message->update(['body' => null, 'deleted_at' => now()]);
        $this->assertNotNull(PlatformNotification::query()->where('type', 'message.received')->firstOrFail()->read_at);
    }

    public function test_suspended_users_and_outside_course_announcement_targets_are_denied(): void
    {
        Bus::fake([SendPlatformNotification::class]);
        $student = $this->user();
        $teacher = $this->user('instructor');
        $outside = $this->course($student);
        $teacher->givePermissionTo(['announcements.manage', 'announcements.publish']);
        Sanctum::actingAs($teacher);
        $payload = ['title' => ['ar' => 'أ', 'en' => 'A'], 'body' => ['ar' => 'ن', 'en' => 'B'], 'audience' => ['kind' => 'course_students', 'course_ids' => [$outside->id]]];
        $this->postJson('/api/v1/admin/announcements', $payload)->assertUnprocessable()->assertJsonValidationErrors('audience.course_ids');
        $student->update(['status' => 'blocked']);
        Sanctum::actingAs($student);
        $this->getJson('/api/v1/notifications')->assertForbidden();
        $this->getJson('/api/v1/notification-preferences')->assertForbidden();
    }

    public function test_workflows_still_succeed_before_permission_registry_is_seeded(): void
    {
        Bus::fake([SendPlatformNotification::class]);
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->for($user)->create();
        $this->assertNotNull($ticket->id);
        $this->assertDatabaseMissing('notifications', ['type' => 'support.created']);
    }

    public function test_published_lessons_only_notify_current_eligible_course_students(): void
    {
        Bus::fake([SendPlatformNotification::class]);
        $student = $this->user();
        $course = $this->course($student);
        $expired = $this->user();
        $outside = $this->user();
        $enrollment = Enrollment::factory()->for($expired)->for($course)->create();
        EnrollmentAccessGrant::factory()->for($enrollment)->create(['access_starts_at' => now()->subDays(3), 'access_expires_at' => now()->subDay()]);
        $section = CourseSection::factory()->for($course)->create();
        Lesson::factory()->for($section, 'section')->create(['is_published' => true]);
        $this->assertSame([$student->id], PlatformNotification::query()->where('type', 'lesson.published')->pluck('notifiable_id')->all());
    }

    public function test_one_broken_scheduled_announcement_does_not_block_later_publication(): void
    {
        $admin = $this->user('admin');
        $fields = ['created_by' => $admin->id, 'title' => ['ar' => 'a', 'en' => 'a'], 'body' => ['ar' => 'a', 'en' => 'a'], 'audience' => ['kind' => 'all'], 'status' => 'scheduled', 'publish_at' => now()->subHour()];
        $expired = Announcement::query()->create([...$fields, 'expires_at' => now()->subMinute()]);
        $valid = Announcement::query()->create($fields);
        $this->artisan('notifications:maintain')->assertSuccessful();
        $this->assertSame('expired', $expired->fresh()->status);
        $this->assertSame('published', $valid->fresh()->status);
    }

    public function test_audience_preview_accepts_only_audience_and_never_returns_user_identities(): void
    {
        $admin = $this->user('admin');
        $student = $this->user();
        $student->assignRole('instructor');
        Sanctum::actingAs($admin);
        $result = $this->postJson('/api/v1/admin/announcements/preview', ['audience' => ['kind' => 'roles', 'roles' => ['student', 'instructor']]])->assertOk()->assertJsonPath('count', 1);
        $this->assertArrayNotHasKey('users', $result->json());
        $this->assertStringNotContainsString($student->email, $result->getContent());
    }

    public function test_queue_failure_alert_is_deduplicated_sanitized_and_excludes_notification_jobs(): void
    {
        $admin = $this->user('admin');
        $job = \Mockery::mock(Job::class);
        $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\ImportCourse');
        $job->shouldReceive('uuid')->andReturn('failed-job-uuid');
        $event = new JobFailed('database', $job, new \RuntimeException('PRIVATE CONTENT'));
        event($event);
        event($event);
        $this->assertSame(1, PlatformNotification::query()->where('type', 'system.critical')->count());
        $this->assertStringNotContainsString('PRIVATE', json_encode(PlatformNotification::query()->where('type', 'system.critical')->first()->data));
        $notificationJob = \Mockery::mock(Job::class);
        $notificationJob->shouldReceive('resolveName')->andReturn(SendPlatformNotification::class);
        event(new JobFailed('database', $notificationJob, new \RuntimeException));
        $this->assertSame(1, PlatformNotification::query()->where('type', 'system.critical')->count());
    }

    public function test_optional_conversation_email_is_aggregated_in_a_delayed_window(): void
    {
        Bus::fake([SendPlatformNotification::class]);
        $student = $this->user();
        $teacher = $this->user('instructor');
        $student->givePermissionTo('messaging.view');
        $teacher->givePermissionTo('messaging.view');
        NotificationPreference::query()->create(['user_id' => $student->id, 'type' => 'message.received', 'in_app' => true, 'email' => true, 'digest' => false]);
        $course = $this->course($student, $teacher);
        $conversation = CourseConversation::query()->create(['course_id' => $course->id, 'instructor_id' => $teacher->id, 'student_id' => $student->id, 'kind' => 'private']);
        foreach (['a', 'b', 'c'] as $id) {
            CourseMessage::query()->create(['course_conversation_id' => $conversation->id, 'user_id' => $teacher->id, 'client_id' => $id, 'body' => 'PRIVATE']);
        }
        $this->assertSame(1, NotificationDelivery::query()->where('type', 'message.received')->count());
        $this->assertTrue(NotificationDelivery::query()->where('type', 'message.received')->first()->available_at->isFuture());
    }

    public function test_management_monitors_and_retries_legacy_outbox_without_exposing_customer_data(): void
    {
        Bus::fake();
        $admin = $this->user('admin');
        $student = $this->user();
        $delivery = TransactionalDelivery::factory()->create(['user_id' => $student->id, 'recipient_email' => 'secret@example.test', 'payload' => ['name' => 'CONFIDENTIAL'], 'status' => 'failed']);
        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/v1/admin/notification-management')->assertOk()->assertJsonPath('legacy_failures.data.0.id', $delivery->id);
        $this->assertStringNotContainsString('CONFIDENTIAL', $response->getContent());
        $this->assertStringNotContainsString('secret@example.test', $response->getContent());
        $this->postJson('/api/v1/admin/notification-management/transactional-deliveries/'.$delivery->id.'/retry')->assertOk();
        $this->assertSame('queued', $delivery->fresh()->status);
        Sanctum::actingAs($student);
        $this->postJson('/api/v1/admin/notification-management/transactional-deliveries/'.$delivery->id.'/retry')->assertForbidden();
    }
}
