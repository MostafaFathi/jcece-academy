<?php

namespace App\Http\Middleware;

use App\Models\CourseConversation;
use App\Policies\CourseConversationPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeCourseMessagingChannel
{
    public function handle(Request $request, Closure $next): Response
    {
        $channel = (string) $request->input('channel_name', '');
        abort_unless(preg_match('/^private-course-conversation\.([0-9]+)$/D', $channel, $match) === 1, 403);
        $conversation = CourseConversation::query()->with('course')->find((int) $match[1]);
        abort_unless($conversation && app(CourseConversationPolicy::class)->view($request->user(), $conversation), 403);

        return $next($request);
    }
}
