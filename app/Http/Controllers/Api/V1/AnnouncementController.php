<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\PlatformNotification;
use App\Services\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function show(Request $request, Announcement $announcement, AnnouncementService $service): JsonResponse
    {
        abort_unless($service->canRead($request->user(), $announcement), 404);
        AnnouncementRecipient::query()->where('announcement_id', $announcement->id)->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        PlatformNotification::query()->where('notifiable_type', $request->user()->getMorphClass())->where('notifiable_id', $request->user()->id)->where('target_type', 'announcement')->where('target_id', $announcement->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['data' => ['id' => $announcement->id, 'title' => $announcement->title, 'body' => $announcement->body, 'published_at' => $announcement->published_at, 'expires_at' => $announcement->expires_at]]);
    }
}
