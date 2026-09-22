<?php

namespace App\Services;

use App\EnrollmentStatus;
use App\LessonProgressStatus;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CourseProgressService
{
    /**
     * @param  EloquentCollection<int, Enrollment>|Collection<int, Enrollment>  $enrollments
     * @return array<int, array{completed_lessons: int, total_lessons: int, progress_percentage: float, resume: ?array{lesson: Lesson, progress: ?LessonProgress}}>
     */
    public function summaries(Collection $enrollments): array
    {
        if ($enrollments->isEmpty()) {
            return [];
        }

        $courseIds = $enrollments->pluck('course_id')->unique()->values();
        $enrollmentIds = $enrollments->pluck('id');

        $lessons = Lesson::query()
            ->select('lessons.*')
            ->addSelect('course_sections.course_id as curriculum_course_id')
            ->join('course_sections', 'course_sections.id', '=', 'lessons.course_section_id')
            ->whereIn('course_sections.course_id', $courseIds)
            ->where('course_sections.is_active', true)
            ->where('lessons.is_published', true)
            ->orderBy('course_sections.sort_order')
            ->orderBy('course_sections.id')
            ->orderBy('lessons.sort_order')
            ->orderBy('lessons.id')
            ->with('section:id,course_id,title,sort_order')
            ->get();

        $lessonIds = $lessons->modelKeys();
        $progressRecords = LessonProgress::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->when($lessonIds === [], fn ($query) => $query->whereRaw('1 = 0'), fn ($query) => $query->whereIn('lesson_id', $lessonIds))
            ->with('lesson.section')
            ->latest('updated_at')
            ->latest('id')
            ->get()
            ->groupBy('enrollment_id');

        $lessonsByCourse = $lessons->groupBy('curriculum_course_id');
        $summaries = [];

        foreach ($enrollments as $enrollment) {
            $courseLessons = $lessonsByCourse->get($enrollment->course_id, collect());
            $enrollmentProgress = $progressRecords->get($enrollment->id, collect());
            $completedLessonIds = $enrollmentProgress
                ->where('status', LessonProgressStatus::Completed)
                ->pluck('lesson_id')
                ->unique();
            $totalLessons = $courseLessons->count();
            $completedLessons = $completedLessonIds->count();
            $recentProgress = $enrollmentProgress->first();
            $resumeLesson = $recentProgress?->lesson ?? $courseLessons->first();

            $summaries[$enrollment->id] = [
                'completed_lessons' => $completedLessons,
                'total_lessons' => $totalLessons,
                'progress_percentage' => $totalLessons === 0 ? 0.0 : round(($completedLessons / $totalLessons) * 100, 2),
                'resume' => $resumeLesson === null ? null : [
                    'lesson' => $resumeLesson,
                    'progress' => $recentProgress,
                ],
            ];
        }

        return $summaries;
    }

    /** @return array{completed_lessons: int, total_lessons: int, progress_percentage: float, resume: ?array{lesson: Lesson, progress: ?LessonProgress}} */
    public function summary(Enrollment $enrollment): array
    {
        return $this->summaries(collect([$enrollment]))[$enrollment->id];
    }

    /** @param array{watched_seconds?: int, last_position_seconds?: int} $attributes */
    public function updateLessonProgress(Enrollment $enrollment, Lesson $lesson, array $attributes): LessonProgress
    {
        $this->ensureLessonIsApplicable($lesson);

        return DB::transaction(function () use ($enrollment, $lesson, $attributes): LessonProgress {
            $progress = LessonProgress::query()
                ->whereBelongsTo($enrollment)
                ->whereBelongsTo($lesson)
                ->lockForUpdate()
                ->firstOrNew([
                    'enrollment_id' => $enrollment->id,
                    'lesson_id' => $lesson->id,
                ]);

            if ($progress->started_at === null) {
                $progress->started_at = now();
            }

            if ($progress->status !== LessonProgressStatus::Completed) {
                $progress->status = LessonProgressStatus::InProgress;
            }

            if (array_key_exists('watched_seconds', $attributes)) {
                $progress->watched_seconds = max($progress->watched_seconds, $attributes['watched_seconds']);
            }

            if (array_key_exists('last_position_seconds', $attributes)) {
                $progress->last_position_seconds = $attributes['last_position_seconds'];
            }

            $progress->save();

            return $progress->refresh();
        });
    }

    public function completeLesson(Enrollment $enrollment, Lesson $lesson): LessonProgress
    {
        $this->ensureLessonIsApplicable($lesson);

        $progress = DB::transaction(function () use ($enrollment, $lesson): LessonProgress {
            $progress = LessonProgress::query()
                ->whereBelongsTo($enrollment)
                ->whereBelongsTo($lesson)
                ->lockForUpdate()
                ->firstOrNew([
                    'enrollment_id' => $enrollment->id,
                    'lesson_id' => $lesson->id,
                ]);

            $progress->status = LessonProgressStatus::Completed;
            $progress->started_at ??= now();
            $progress->completed_at ??= now();
            $progress->save();

            return $progress->refresh();
        });

        $summary = $this->summary($enrollment);

        if ($summary['total_lessons'] > 0 && $summary['completed_lessons'] === $summary['total_lessons']) {
            $enrollment->update([
                'status' => EnrollmentStatus::Completed,
                'completed_at' => $enrollment->completed_at ?? now(),
            ]);
        }

        return $progress;
    }

    private function ensureLessonIsApplicable(Lesson $lesson): void
    {
        $lesson->loadMissing('section');

        abort_unless($lesson->is_published && $lesson->section->is_active, 404);
    }
}
