<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CatalogImageStorage
{
    public function __construct(private UploadedFileStorage $uploads) {}

    public function store(UploadedFile $file, string $directory, string $validationKey): string
    {
        $path = $this->uploads->store($file, $directory, 'public', $validationKey);

        return Storage::disk('public')->url($path);
    }

    public function deleteManaged(?string $url): void
    {
        $path = parse_url($url ?? '', PHP_URL_PATH);
        if (is_string($path) && preg_match('~^/storage/(catalog/(?:categories|courses|packages)/[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp))$~', $path, $matches)) {
            Storage::disk('public')->delete($matches[1]);
        }
    }
}
