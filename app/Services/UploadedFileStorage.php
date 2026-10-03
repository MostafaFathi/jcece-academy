<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class UploadedFileStorage
{
    public function store(UploadedFile $file, string $directory, string $disk, string $validationKey): string
    {
        $temporaryPath = $file->getPathname();
        if (! $file->isValid() || $temporaryPath === '' || ! is_readable($temporaryPath)) {
            throw ValidationException::withMessages([$validationKey => 'The uploaded file is no longer available. Please select it again.']);
        }

        $path = $file->getRealPath() === false
            ? Storage::disk($disk)->putFileAs($directory, $temporaryPath, $file->hashName())
            : $file->store($directory, $disk);

        if ($path === false) {
            throw new RuntimeException('The uploaded file could not be stored.');
        }

        return $path;
    }
}
