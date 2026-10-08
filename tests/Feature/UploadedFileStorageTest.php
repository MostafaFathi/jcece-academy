<?php

namespace Tests\Feature;

use App\Services\UploadedFileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadedFileStorageTest extends TestCase
{
    public function test_stores_upload_when_windows_cannot_resolve_its_real_path(): void
    {
        Storage::fake('public');
        $source = UploadedFile::fake()->image('avatar.png', 64, 64);
        $upload = new class($source->getPathname(), 'avatar.png', 'image/png', UPLOAD_ERR_OK, true) extends UploadedFile
        {
            public function getRealPath(): string|false
            {
                return false;
            }
        };

        $path = app(UploadedFileStorage::class)->store($upload, 'avatars', 'public', 'avatar');

        $this->assertStringStartsWith('avatars/', $path);
        Storage::disk('public')->assertExists($path);
    }
}
