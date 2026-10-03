<?php

namespace App\Services;

use App\Models\LessonResource;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LessonResourceFileService
{
    public const PATH_PATTERN = '~\Alesson-resources/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-][a-zA-Z0-9_.-]*\z~D';

    public static function hasSafeFile(LessonResource $resource): bool
    {
        return is_string($resource->file_path)
            && mb_strlen($resource->file_path) <= 255
            && preg_match(self::PATH_PATTERN, $resource->file_path) === 1;
    }

    public static function safeExternalUrl(LessonResource $resource): ?string
    {
        if (filled($resource->file_path) || ! is_string($resource->external_url)) {
            return null;
        }

        $url = $resource->external_url;
        $parts = parse_url($url);
        $decodedUrl = $url;

        for ($iteration = 0; $iteration < 5; $iteration++) {
            $decodedUrl = rawurldecode($decodedUrl);
        }

        if ($parts === false || ! in_array(mb_strtolower($parts['scheme'] ?? ''), ['https', 'http'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || str_contains($decodedUrl, '%') || str_contains($decodedUrl, '\\')
            || preg_match('~[\x00-\x20\x7f]|storage|private|lesson-resources|file_path|storage_path|storage_disk|x-amz-|x-goog-|signature=~i', $decodedUrl)) {
            return null;
        }

        return $url;
    }

    public function download(LessonResource $resource, bool $requiresDownloadable = true): StreamedResponse
    {
        abort_unless((! $requiresDownloadable || $resource->is_downloadable) && self::hasSafeFile($resource), 404);

        $disk = Storage::disk('lesson_resources');
        $key = substr($resource->file_path, mb_strlen('lesson-resources/'));
        $root = realpath($disk->path(''));
        $path = realpath($disk->path($key));

        abort_unless($root !== false && $path !== false && is_file($path)
            && str_starts_with($path, $root.DIRECTORY_SEPARATOR), 404);

        $extension = mb_strtolower(pathinfo($key, PATHINFO_EXTENSION));
        $extension = in_array($extension, ['pdf', 'txt', 'csv', 'zip', 'docx', 'xlsx', 'pptx', 'png', 'jpg', 'jpeg', 'mp3', 'mp4'], true) ? $extension : 'bin';

        return $disk->download($key, "lesson-resource-{$resource->id}.{$extension}", [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
