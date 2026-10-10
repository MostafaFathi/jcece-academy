<?php

use App\Models\CourseConversation;
use App\Models\User;
use App\Policies\CourseConversationPolicy;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('course-conversation.{conversation}', function (User $user, int $conversation): bool {
    $model = CourseConversation::query()->with('course')->find($conversation);

    return $model !== null && app(CourseConversationPolicy::class)->view($user, $model);
}, ['guards' => ['sanctum']]);
