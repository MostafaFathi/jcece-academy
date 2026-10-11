<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendPlatformNotification;
use App\Models\NotificationDelivery;
use App\Models\NotificationTypePolicy;
use App\Models\TransactionalDelivery;
use App\PermissionName;
use App\Services\AuditTrail;
use App\Services\NotificationTypeRegistry;
use App\Services\TransactionalDeliveryService;
use App\UserStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NotificationManagementController extends Controller
{
    public function __construct(private NotificationTypeRegistry $registry, private AuditTrail $audit) {}

    private function authorizeStaff(Request $request): void
    {
        abort_unless($request->user()->status === UserStatus::Active && $request->user()->can('notifications.manage'), 403);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);
        $catalog = [];
        foreach ($this->registry->all() as $type => $definition) {
            $policy = NotificationTypePolicy::query()->where('type', $type)->first();
            $catalog[] = ['type' => $type, 'mandatory_email' => $definition['mandatory'] && $definition['email'], ...$definition, 'in_app' => $policy?->in_app ?? true, 'email' => $policy?->email ?? $definition['email']];
        }

        return response()->json(['catalog' => $catalog, 'statistics' => NotificationDelivery::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'), 'can_retry_legacy' => $request->user()->can(PermissionName::TransactionalDeliveriesRetry->value), 'legacy_failures' => TransactionalDelivery::query()->where('status', 'failed')->select(['id', 'message_type', 'status', 'attempts', 'error_code', 'updated_at'])->orderByDesc('id')->paginate(20, ['*'], 'legacy_page'), 'legacy_statistics' => TransactionalDelivery::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'), 'failures' => NotificationDelivery::query()->where('status', 'failed')->select(['id', 'type', 'status', 'attempts', 'error_code', 'updated_at'])->orderByDesc('id')->paginate(20)]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);
        $data = $request->validate(['type' => ['required', Rule::in(array_keys($this->registry->all()))], 'in_app' => ['required', 'boolean'], 'email' => ['required', 'boolean']]);
        $definition = $this->registry->get($data['type']);
        if ($definition['mandatory']) {
            abort_unless($data['in_app'] && (! $definition['email'] || $data['email']), 422);
        }
        DB::transaction(function () use ($request, $data): void {
            $policy = NotificationTypePolicy::query()->updateOrCreate(['type' => $data['type']], $data);
            $this->audit->record('notification.policy.updated', $policy, $request->user(), ['changed_fields' => 'in_app,email']);
        });

        return response()->json(['status' => 'updated']);
    }

    public function retryTransactional(Request $request, TransactionalDelivery $delivery): JsonResponse
    {
        $this->authorizeStaff($request);
        abort_unless($request->user()->can(PermissionName::TransactionalDeliveriesRetry->value), 403);
        app(TransactionalDeliveryService::class)->retry($delivery, $request->user(), $this->audit);

        return response()->json(['status' => 'queued']);
    }

    public function retry(Request $request, NotificationDelivery $delivery): JsonResponse
    {
        $this->authorizeStaff($request);
        DB::transaction(function () use ($delivery, $request): void {
            $d = NotificationDelivery::query()->lockForUpdate()->findOrFail($delivery->id);
            abort_unless($d->status === 'failed', 422);
            $d->update(['status' => 'queued', 'available_at' => now(), 'error_code' => null]);
            $this->audit->record('notification.delivery.retry', $d, $request->user(), ['from_status' => 'failed', 'to_status' => 'queued']);
            SendPlatformNotification::dispatch($d->id)->afterCommit();
        });

        return response()->json(['status' => 'queued']);
    }
}
