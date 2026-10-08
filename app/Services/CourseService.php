<?php

namespace App\Services;

use App\CourseStatus;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CourseService
{
    public function __construct(private AuditTrail $audit) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, User $actor): Course
    {
        return $this->persist(new Course, $attributes, $actor);
    }

    /** @param array<string, mixed> $attributes */
    public function update(Course $course, array $attributes, User $actor): Course
    {
        return $this->persist($course, $attributes, $actor);
    }

    /** @param array<string, mixed> $attributes */
    private function persist(Course $course, array $attributes, User $actor): Course
    {
        $orderedRelations = [
            'learning_outcomes' => ['learningOutcomes', 'outcome'],
            'requirements' => ['requirements', 'requirement'],
            'target_audiences' => ['targetAudiences', 'audience'],
            'required_tools' => ['requiredTools', 'tool'],
        ];
        $relationValues = [];

        foreach ($orderedRelations as $inputKey => $relationDefinition) {
            if (array_key_exists($inputKey, $attributes)) {
                $relationValues[$inputKey] = $attributes[$inputKey];
                unset($attributes[$inputKey]);
            }
        }

        return DB::transaction(function () use ($course, $attributes, $actor, $orderedRelations, $relationValues): Course {
            if ($course->exists) {
                $course = Course::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();
            }

            $attributes['updated_by'] = $actor->id;

            if ($course->exists && array_key_exists('certificate_enabled', $attributes)
                && (bool) $attributes['certificate_enabled'] !== $course->certificate_enabled) {
                $course->certificate_requirements_version++;
            }

            if (! $course->exists) {
                $attributes['created_by'] = $actor->id;
            }

            if (($attributes['status'] ?? null) === CourseStatus::Published->value && empty($attributes['published_at']) && $course->published_at === null) {
                $attributes['published_at'] = now();
            }

            $previousStatus = $course->status?->value;
            $course->fill($attributes)->save();
            if ($previousStatus !== $course->status?->value && in_array($course->status, [CourseStatus::Published, CourseStatus::Archived], true)) {
                $this->audit->record('course.status_changed', $course, $actor, ['from_status' => $previousStatus, 'to_status' => $course->status->value]);
            }

            foreach ($relationValues as $inputKey => $values) {
                [$relationName, $valueColumn] = $orderedRelations[$inputKey];
                /** @var HasMany $relation */
                $relation = $course->{$relationName}();
                $relation->delete();
                $relation->createMany(collect($values)->values()->map(
                    fn (string $value, int $sortOrder): array => [
                        $valueColumn => $value,
                        'sort_order' => $sortOrder,
                    ],
                ));
            }

            return $course->fresh([
                'category',
                'instructor',
                'learningOutcomes',
                'requirements',
                'targetAudiences',
                'requiredTools',
            ]);
        });
    }
}
