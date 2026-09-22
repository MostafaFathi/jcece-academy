<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CurriculumOrderService
{
    /** @param list<int> $ids */
    public function reorderSections(Course $course, array $ids): void
    {
        $this->reorder($course, 'sections', $ids);
    }

    /** @param list<int> $ids */
    public function reorderLessons(CourseSection $section, array $ids): void
    {
        $this->reorder($section, 'lessons', $ids);
    }

    /** @param list<int> $ids */
    public function reorderResources(Lesson $lesson, array $ids): void
    {
        $this->reorder($lesson, 'resources', $ids);
    }

    /** @param list<int> $ids */
    private function reorder(Model $parent, string $relationship, array $ids): void
    {
        DB::transaction(function () use ($parent, $relationship, $ids): void {
            $relation = $parent->{$relationship}();
            $ownedIds = $relation->lockForUpdate()->pluck($relation->getRelated()->getQualifiedKeyName())->map(fn (mixed $id): int => (int) $id)->all();
            $expectedIds = $ids;
            sort($ownedIds);
            sort($expectedIds);

            if ($ownedIds !== $expectedIds) {
                throw ValidationException::withMessages([
                    'ids' => 'The supplied IDs must exactly match the items owned by this parent.',
                ]);
            }

            foreach ($ids as $sortOrder => $id) {
                $parent->{$relationship}()->whereKey($id)->update(['sort_order' => $sortOrder]);
            }
        });
    }
}
