<?php

namespace App\Services;

use App\AccessGrantSource;
use App\Models\Course;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemPackageCourse;
use App\Models\User;
use App\OrderStatus;
use App\PurchasableType;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderAccessProvisioningService
{
    public function __construct(private CourseAccessService $courseAccess, private AuditTrail $audit, private FinancialDocumentService $documents, private TransactionalDeliveryService $deliveries) {}

    /**
     * The database-unique entitlement key identifies an Order Item plus Course
     * for direct purchases, or an Order Item plus package-course snapshot row.
     *
     * @return array{order: Order, grants_created: int, grants_existing: int}
     */
    public function provision(Order $order): array
    {
        return DB::transaction(function () use ($order): array {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($lockedOrder->status, [OrderStatus::Paid, OrderStatus::Completed], true)) {
                throw ValidationException::withMessages([
                    'order' => 'Only paid orders may be provisioned.',
                ]);
            }

            if ($lockedOrder->paid_at === null) {
                throw ValidationException::withMessages([
                    'order' => 'The paid order is missing its activation timestamp.',
                ]);
            }

            $items = OrderItem::query()
                ->whereBelongsTo($lockedOrder)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'order' => 'The paid order has no purchase snapshots to provision.',
                ]);
            }

            $user = User::query()->findOrFail($lockedOrder->user_id);
            $created = 0;
            $existing = 0;

            foreach ($items as $item) {
                $type = PurchasableType::tryFrom($item->purchasable_type);

                if ($type === null) {
                    throw ValidationException::withMessages([
                        'order' => "Order item {$item->id} has an unsupported purchase type.",
                    ]);
                }

                if ($type === PurchasableType::Course) {
                    $course = $this->resolveHistoricalCourse($item->purchasable_id, "order item {$item->id}");
                    $result = $this->grant(
                        $user,
                        $course,
                        AccessGrantSource::DirectPurchase,
                        $item->id,
                        "course-order-item:{$item->id}:course:{$course->id}",
                        $lockedOrder->paid_at,
                        $item->access_duration_days,
                    );
                    $result['created'] ? $created++ : $existing++;

                    continue;
                }

                $snapshots = OrderItemPackageCourse::query()
                    ->whereBelongsTo($item)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($snapshots->isEmpty()) {
                    throw ValidationException::withMessages([
                        'order' => "Package order item {$item->id} has no course snapshots.",
                    ]);
                }

                foreach ($snapshots as $snapshot) {
                    if ($snapshot->course_id === null) {
                        throw ValidationException::withMessages([
                            'order' => "Package course snapshot {$snapshot->id} references a permanently deleted course.",
                        ]);
                    }

                    $course = $this->resolveHistoricalCourse($snapshot->course_id, "package course snapshot {$snapshot->id}");
                    $result = $this->grant(
                        $user,
                        $course,
                        AccessGrantSource::PackagePurchase,
                        $item->id,
                        "package-order-item:{$item->id}:snapshot:{$snapshot->id}",
                        $lockedOrder->paid_at,
                        $item->access_duration_days,
                    );
                    $result['created'] ? $created++ : $existing++;
                }
            }

            if ($lockedOrder->status === OrderStatus::Paid) {
                $lockedOrder->update(['status' => OrderStatus::Completed]);
                $this->audit->record('order.status_changed', $lockedOrder, null, ['from_status' => OrderStatus::Paid->value, 'to_status' => OrderStatus::Completed->value]);
                $this->documents->schedulePurchase($lockedOrder);
                $this->deliveries->recordForOrder('purchase_completed', 'Order', $lockedOrder->id, $lockedOrder);
            }

            if ($created > 0) {
                $this->audit->record('order.access_provisioned', $lockedOrder, null, ['grants_created' => $created, 'grants_existing' => $existing]);
            }

            return [
                'order' => $lockedOrder->refresh()->load(['user', 'items.packageCourses', 'payments.approver']),
                'grants_created' => $created,
                'grants_existing' => $existing,
            ];
        }, 3);
    }

    /** @return array{created: bool} */
    private function grant(
        User $user,
        Course $course,
        AccessGrantSource $source,
        int $sourceId,
        string $entitlementKey,
        CarbonInterface $startsAt,
        ?int $durationDays,
    ): array {
        $expiresAt = $durationDays === null
            ? null
            : $startsAt->toImmutable()->addDays($durationDays);

        $result = $this->courseAccess->grantPurchasedAccess(
            $user,
            $course,
            $source,
            $sourceId,
            $entitlementKey,
            $startsAt,
            $expiresAt,
        );

        return ['created' => $result['created']];
    }

    private function resolveHistoricalCourse(int $courseId, string $snapshot): Course
    {
        $course = Course::withTrashed()->find($courseId);

        if ($course === null) {
            throw ValidationException::withMessages([
                'order' => "The {$snapshot} references a missing historical course.",
            ]);
        }

        return $course;
    }
}
