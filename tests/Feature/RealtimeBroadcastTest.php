<?php

namespace Tests\Feature;

use App\Events\CourseConversationChanged;
use App\Events\CourseConversationTyping;
use App\Events\UserNotificationChanged;
use App\Models\User;
use App\RoleName;
use App\Services\RealtimeSignals;
use App\UserStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class RealtimeBroadcastTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_notification_channel_is_private_to_its_active_owner_without_messaging_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $owner = User::factory()->create();
        $owner->assignRole(RoleName::Admin->value);
        $other = User::factory()->create();

        $this->postJson('/api/v1/broadcasting/auth', ['channel_name' => 'private-user-notifications.'.$owner->id, 'socket_id' => '1.1'])->assertUnauthorized();

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/broadcasting/auth', ['channel_name' => 'private-user-notifications.'.$owner->id, 'socket_id' => '1.1'])->assertOk();
        $this->postJson('/api/v1/broadcasting/auth', ['channel_name' => 'private-user-notifications.'.$other->id, 'socket_id' => '1.1'])->assertForbidden();
        $this->postJson('/api/v1/broadcasting/auth', ['channel_name' => 'private-unrelated.1', 'socket_id' => '1.1'])->assertForbidden();

        $owner->update(['status' => UserStatus::Blocked]);
        $this->postJson('/api/v1/broadcasting/auth', ['channel_name' => 'private-user-notifications.'.$owner->id, 'socket_id' => '1.1'])->assertForbidden();
    }

    public function test_realtime_signals_use_private_channels_and_contain_no_message_or_notification_data(): void
    {
        $conversation = new CourseConversationChanged(12, 5, 'message');
        $typing = new CourseConversationTyping(12, 9, true);
        $notification = new UserNotificationChanged(9);

        $this->assertInstanceOf(PrivateChannel::class, $conversation->broadcastOn()[0]);
        $this->assertSame('private-course-conversation.12', $conversation->broadcastOn()[0]->name);
        $this->assertSame('private-user-notifications.9', $notification->broadcastOn()[0]->name);
        $this->assertSame([], $notification->broadcastWith());
        $this->assertSame(['conversation_id' => 12, 'version' => 5, 'kind' => 'message'], $conversation->broadcastWith());
        $this->assertSame('private-course-conversation.12', $typing->broadcastOn()[0]->name);
        $this->assertSame(['userId' => 9, 'typing' => true], $typing->broadcastWith());
    }

    public function test_configured_signals_dispatch_immediately_after_commit_without_a_queue_worker(): void
    {
        config()->set('broadcasting.default', 'pusher');
        config()->set('broadcasting.connections.pusher', ['key' => 'public-key', 'secret' => 'test-secret', 'app_id' => '123', 'options' => ['cluster' => 'eu']]);
        config()->set('messaging.broadcast', true);
        config()->set('platform_notifications.broadcast', true);
        Event::fake([CourseConversationChanged::class, CourseConversationTyping::class, UserNotificationChanged::class]);
        DB::shouldReceive('afterCommit')->times(3)->andReturnUsing(function ($callback): void {
            $callback();
        });

        $signals = app(RealtimeSignals::class);
        $signals->conversation(12, 5, 'message');
        $signals->typing(12, 9, true);
        $signals->notification(9);

        Event::assertDispatched(CourseConversationChanged::class, fn (CourseConversationChanged $event): bool => $event->conversationId === 12);
        Event::assertDispatched(CourseConversationTyping::class, fn (CourseConversationTyping $event): bool => $event->conversationId === 12 && $event->userId === 9 && $event->typing);
        Event::assertDispatched(UserNotificationChanged::class, fn (UserNotificationChanged $event): bool => $event->userId === 9);
    }

    public function test_unconfigured_pusher_does_not_attempt_a_broadcast(): void
    {
        config()->set('messaging.broadcast', true);
        config()->set('platform_notifications.broadcast', true);
        Event::fake([CourseConversationChanged::class, UserNotificationChanged::class]);

        $signals = app(RealtimeSignals::class);
        $signals->conversation(12, 5, 'message');
        $signals->notification(9);

        Event::assertNotDispatched(CourseConversationChanged::class);
        Event::assertNotDispatched(UserNotificationChanged::class);
    }

    public function test_pusher_failure_does_not_fail_the_saved_operation(): void
    {
        config()->set('broadcasting.default', 'pusher');
        config()->set('broadcasting.connections.pusher', ['key' => 'public-key', 'secret' => 'test-secret', 'app_id' => '123', 'options' => ['cluster' => 'eu']]);
        config()->set('messaging.broadcast', true);
        DB::shouldReceive('afterCommit')->once()->andReturnUsing(function ($callback): void {
            $callback();
        });
        Event::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Pusher unavailable'));
        Log::shouldReceive('warning')->once();

        app(RealtimeSignals::class)->conversation(12, 5, 'message');
    }

    public function test_spa_bootstrap_exposes_only_the_public_pusher_key(): void
    {
        config()->set('broadcasting.default', 'pusher');
        config()->set('broadcasting.connections.pusher.key', 'public-key');
        config()->set('broadcasting.connections.pusher.secret', 'private-secret');
        config()->set('broadcasting.connections.pusher.options.cluster', 'eu');

        $this->get('/')->assertOk()->assertSee('public-key')->assertDontSee('private-secret');
    }
}
