<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonResource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class LessonResourceUploadService
{
    public function __construct(private UploadedFileStorage $files) {}

    public function store(Lesson $lesson, UploadedFile $file, string $title, bool $isDownloadable): LessonResource
    {
        $courseId = $lesson->section->course_id;
        $key = $this->files->store($file, "courses/{$courseId}/lessons/{$lesson->id}", 'lesson_resources', 'file');

        try {
            return DB::transaction(fn (): LessonResource => $lesson->resources()->create([
                'title' => $title,
                'type' => 'file',
                'file_path' => 'lesson-resources/'.$key,
                'external_url' => null,
                'is_downloadable' => $isDownloadable,
                'sort_order' => (int) $lesson->resources()->max('sort_order') + 1,
            ]));
        } catch (Throwable $exception) {
            Storage::disk('lesson_resources')->delete($key);

            throw $exception;
        }
    }

    public function deleteManagedFileIfUnused(string $path): void
    {
        if (! str_starts_with($path, 'lesson-resources/courses/') || LessonResource::query()->where('file_path', $path)->exists()) {
            return;
        }

        Storage::disk('lesson_resources')->delete(substr($path, mb_strlen('lesson-resources/')));
    }
}
