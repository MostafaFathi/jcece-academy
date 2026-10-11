<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PreviewAnnouncementAudienceRequest;
use App\Http\Requests\SaveAnnouncementRequest;
use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Services\AnnouncementService;
use App\Services\AuditTrail;
use App\UserStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnnouncementManagementController extends Controller
{
    public function __construct(private AnnouncementService $service, private AuditTrail $audit) {}

    private function authorizeStaff(Request $request): void
    {
        abort_unless($request->user()->status === UserStatus::Active && $request->user()->can('announcements.manage'), 403);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);
        $items = Announcement::query()->orderByDesc('id')->paginate(20);
        $items->through(function ($a): array {
            return [...$a->toArray(), 'recipients' => AnnouncementRecipient::query()->where('announcement_id', $a->id)->count(), 'read_count' => AnnouncementRecipient::query()->where('announcement_id', $a->id)->whereNotNull('read_at')->count()];
        });

        return response()->json($items);
    }

    public function store(SaveAnnouncementRequest $request): JsonResponse
    {
        $data = $request->validated();
        $a = DB::transaction(function () use ($request, $data): Announcement {
            $a = Announcement::query()->create([...$data, 'created_by' => $request->user()->id, 'status' => empty($data['publish_at']) ? 'draft' : 'scheduled']);
            $this->audit->record('announcement.created', $a, $request->user(), ['to_status' => $a->status]);

            return $a;
        });

        return response()->json(['data' => $a], 201);
    }

    public function update(SaveAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        return DB::transaction(function () use ($request, $announcement): JsonResponse {
            $a = Announcement::query()->lockForUpdate()->findOrFail($announcement->id);
            abort_unless(in_array($a->status, ['draft', 'scheduled'], true), 422);
            $data = $request->validated();
            $a->update([...$data, 'status' => empty($data['publish_at']) ? 'draft' : 'scheduled']);
            $this->audit->record('announcement.updated', $a, $request->user(), ['changed_fields' => implode(',', array_keys($data))]);

            return response()->json(['data' => $a]);
        });
    }

    public function preview(PreviewAnnouncementAudienceRequest $request): JsonResponse
    {
        $count = 0;
        foreach ($this->service->audience($request->validated('audience')) as $user) {
            $count++;
        }

        return response()->json(['count' => $count, 'semantics' => 'snapshot_at_publication_with_current_eligibility_on_read']);
    }

    public function publish(Request $request, Announcement $announcement): JsonResponse
    {
        $this->authorizeStaff($request);

        return response()->json(['data' => $this->service->publish($announcement, $request->user())]);
    }

    public function archive(Request $request, Announcement $announcement): JsonResponse
    {
        $this->authorizeStaff($request);
        abort_unless($request->user()->can('announcements.publish'), 403);
        DB::transaction(function () use ($request, $announcement): void {
            $a = Announcement::query()->lockForUpdate()->findOrFail($announcement->id);
            $a->update(['status' => 'archived']);
            $this->audit->record('announcement.archived', $a, $request->user(), ['to_status' => 'archived']);
        });

        return response()->json(['status' => 'archived']);
    }
}
