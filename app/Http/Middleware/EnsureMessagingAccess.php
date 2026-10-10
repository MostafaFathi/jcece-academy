<?php

namespace App\Http\Middleware;

use App\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMessagingAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->status === UserStatus::Active && $request->user()->can('messaging.view'), 403);

        return $next($request);
    }
}
