<?php

namespace App\Services;

use App\Models\Package;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PackageCourseOrderService
{
    /** @param list<int> $ids */
    public function reorder(Package $package, array $ids): void
    {
        DB::transaction(function () use ($package, $ids): void {
            $ownedIds = $package->courseMemberships()
                ->lockForUpdate()
                ->pluck('package_courses.id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
            $expectedIds = $ids;
            sort($ownedIds);
            sort($expectedIds);

            if ($ownedIds !== $expectedIds) {
                throw ValidationException::withMessages([
                    'ids' => 'The supplied IDs must exactly match the course memberships owned by this package.',
                ]);
            }

            foreach ($ids as $sortOrder => $id) {
                $package->courseMemberships()->whereKey($id)->update(['sort_order' => $sortOrder]);
            }
        });
    }
}
