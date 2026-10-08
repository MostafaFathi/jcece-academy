<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AuthenticatedUserResource;
use App\Services\UploadedFileStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AvatarController extends Controller
{
    public function store(Request $request, UploadedFileStorage $uploads): AuthenticatedUserResource
    {
        $request->validate(['avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        $user = $request->user();
        $oldAvatar = $user->avatar;
        $path = $uploads->store($request->file('avatar'), 'avatars', 'public', 'avatar');

        try {
            $user->update(['avatar' => Storage::disk('public')->url($path)]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        $this->deleteManagedAvatar($oldAvatar);

        return new AuthenticatedUserResource($user->refresh()->load('instructorProfile'));
    }

    public function destroy(Request $request): AuthenticatedUserResource
    {
        $user = $request->user();
        $oldAvatar = $user->avatar;
        $user->update(['avatar' => null]);
        $this->deleteManagedAvatar($oldAvatar);

        return new AuthenticatedUserResource($user->refresh()->load('instructorProfile'));
    }

    private function deleteManagedAvatar(?string $url): void
    {
        $path = parse_url($url ?? '', PHP_URL_PATH);
        if (is_string($path) && preg_match('~^/storage/(avatars/[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp))$~', $path, $matches)) {
            Storage::disk('public')->delete($matches[1]);
        }
    }
}
