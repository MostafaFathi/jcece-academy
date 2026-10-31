<?php

namespace App\Services;

use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\LessonVideoUpload;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class CurriculumDeletionService
{
    public function __construct(private BunnyStreamClient $bunny) {}

    public function deleteLesson(Lesson $lesson): int
    {
        return DB::transaction(function () use ($lesson): int {
            $lockedLesson = Lesson::query()->whereKey($lesson->id)->lockForUpdate()->firstOrFail();

            return $this->deleteLessons(collect([$lockedLesson]));
        });
    }

    public function deleteSection(CourseSection $section): int
    {
        return DB::transaction(function () use ($section): int {
            $lockedSection = CourseSection::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();
            $lessons = $lockedSection->lessons()->lockForUpdate()->get();
            $failedVideoCount = $this->deleteLessons($lessons);
            $lockedSection->delete();

            return $failedVideoCount;
        });
    }

    /** @param Collection<int, Lesson> $lessons */
    private function deleteLessons(Collection $lessons): int
    {
        if ($lessons->isEmpty()) {
            return 0;
        }

        $uploads = LessonVideoUpload::query()
            ->whereIn('lesson_id', $lessons->pluck('id')->all())
            ->where('status', '!=', 'deleted')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        abort_if($uploads->contains(fn (LessonVideoUpload $upload): bool => blank($upload->video_guid)), 409, 'Reconcile uncertain video uploads before deleting curriculum.');

        $uploadsByLesson = $uploads->groupBy('lesson_id');
        $failedVideoCount = 0;

        foreach ($lessons as $lesson) {
            foreach ($uploadsByLesson->get($lesson->id, []) as $upload) {
                try {
                    $this->bunny->delete($upload->video_guid);
                    $upload->update(['status' => 'deleted', 'is_current' => false, 'deleted_at' => now()]);
                } catch (ServiceUnavailableHttpException) {
                    $upload->update(['status' => 'cleanup_failed', 'is_current' => false]);
                    $failedVideoCount++;
                }
            }

            if ($lesson->video_provider === 'bunny_stream') {
                $lesson->update(['protected_video_asset_key' => null, 'video_provider' => null]);
            }

            $lesson->delete();
        }

        return $failedVideoCount;
    }
}
