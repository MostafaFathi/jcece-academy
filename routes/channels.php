<?php

use App\Models\CourseConversation;
use App\Models\User;
use App\Policies\CourseConversationPolicy;
use App\UserStatus;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('course-conversation.{conversation}', function (User $user, int $conversation): bool {
    $model = CourseConversation::query()->with('course')->find($conversation);

    return $model !== null && app(CourseConversationPolicy::class)->view($user, $model);
}, ['guards' => ['sanctum']]);

Broadcast::channel('user-notifications.{userId}', fn (User $user, int $userId): bool => $user->id === $userId && $user->status === UserStatus::Active, ['guards' => ['sanctum']]);
