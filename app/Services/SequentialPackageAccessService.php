<?php

namespace App\Services;

use App\AccessGrantSource;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\OrderItem;
use App\Models\OrderItemPackageCourse;
use Brick\Math\BigDecimal;

class SequentialPackageAccessService
{
    public function __construct(private CourseProgressService $progress) {}

    /**
     * Null means that no new sequential snapshot governs this grant. Legacy
     * purchases therefore retain the access they had before this deployment.
     *
     * @return null|array{unlocked: bool, course_order: int, course_count: int, required_percentage: string, previous_course_title: ?string, previous_progress_percentage: float}
     */
    public function state(EnrollmentAccessGrant $grant, Enrollment $enrollment): ?array
    {
        if ($grant->source_type !== AccessGrantSource::PackagePurchase || $grant->source_id === null) {
            return null;
        }

        $item = OrderItem::query()->find($grant->source_id);

        if ($item === null) {
            return ['unlocked' => false, 'course_order' => 0, 'course_count' => 0, 'required_percentage' => '0.00', 'previous_course_title' => null, 'previous_progress_percentage' => 0.0];
        }

        if ($item->sequential_completion_percentage === null) {
            return null;
        }

        $snapshots = OrderItemPackageCourse::query()
            ->whereBelongsTo($item)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'order_item_id', 'course_id', 'course_title', 'sort_order']);
        $position = $snapshots->search(fn (OrderItemPackageCourse $snapshot): bool => $snapshot->course_id === $enrollment->course_id);
        $previous = $position === false || $position === 0 ? null : $snapshots->get($position - 1);
        $previousEnrollment = $previous?->course_id === null
            ? null
            : Enrollment::query()->where('user_id', $enrollment->user_id)->where('course_id', $previous->course_id)->first();
        $summary = $previousEnrollment === null ? null : $this->progress->summary($previousEnrollment);
        $required = $item->sequential_completion_percentage;
        $unlocked = $position === 0 || ($summary !== null
            && $summary['total_lessons'] > 0
            && BigDecimal::of($summary['completed_lessons'])->multipliedBy(100)
                ->isGreaterThanOrEqualTo(BigDecimal::of($required)->multipliedBy($summary['total_lessons'])));

        return [
            'unlocked' => $unlocked,
            'course_order' => $position === false ? 0 : $position + 1,
            'course_count' => $snapshots->count(),
            'required_percentage' => $required,
            'previous_course_title' => $previous?->course_title,
            'previous_progress_percentage' => $summary['progress_percentage'] ?? 0.0,
        ];
    }
}
