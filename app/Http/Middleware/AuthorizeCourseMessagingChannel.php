<?php

namespace App\Http\Middleware;

use App\Models\CourseConversation;
use App\Policies\CourseConversationPolicy;
use App\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeCourseMessagingChannel
{
    public function handle(Request $request, Closure $next): Response
    {
        $channel = (string) $request->input('channel_name', '');

        if (preg_match('/^private-user-notifications\.([0-9]+)$/D', $channel, $userMatch) === 1) {
            abort_unless($request->user()?->status === UserStatus::Active && $request->user()->id === (int) $userMatch[1], 403);

            return $next($request);
        }

        abort_unless(preg_match('/^private-course-conversation\.([0-9]+)$/D', $channel, $match) === 1, 403);
        $conversation = CourseConversation::query()->with('course')->find((int) $match[1]);
        abort_unless($conversation && app(CourseConversationPolicy::class)->view($request->user(), $conversation), 403);

        return $next($request);
    }
}
