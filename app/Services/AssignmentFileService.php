<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentAttachment;
use App\Models\AssignmentSubmission;
use App\Models\AssignmentSubmissionFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class AssignmentFileService
{
    public function __construct(
        private AssignmentSubmissionService $submissions,
        private AssignmentAccessService $access,
    ) {}

    public function storeAttachment(Assignment $assignment, UploadedFile $file): AssignmentAttachment
    {
        $disk = (string) config('jcec.assignments.file_disk', 'local');
        $path = null;

        try {
            return DB::transaction(function () use ($assignment, $file, $disk, &$path): AssignmentAttachment {
                $lockedAssignment = Assignment::query()->lockForUpdate()->findOrFail($assignment->id);
                $path = $file->store("assignment-attachments/{$lockedAssignment->id}", $disk);

                if ($path === false) {
                    throw new RuntimeException('The assignment attachment could not be stored.');
                }

                return $lockedAssignment->attachments()->create($this->fileAttributes($file, $disk, $path));
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                Storage::disk($disk)->delete($path);
            }

            throw $exception;
        }
    }

    /** @param list<UploadedFile> $files */
    public function storeSubmissionFiles(User $user, AssignmentSubmission $submission, array $files): AssignmentSubmission
    {
        $disk = (string) config('jcec.assignments.file_disk', 'local');
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($user, $submission, $files, $disk, &$storedPaths): AssignmentSubmission {
                $lockedSubmission = AssignmentSubmission::query()->lockForUpdate()->findOrFail($submission->id);
                $this->submissions->authorizeOwner($user, $lockedSubmission);
                $this->submissions->ensureDraft($lockedSubmission);
                $this->access->requireAvailable($user, $lockedSubmission->assignment()->with('course')->firstOrFail());
                $maximumFiles = (int) config('jcec.assignments.submission_file_max_count', 5);

                if ($lockedSubmission->files()->count() + count($files) > $maximumFiles) {
                    throw ValidationException::withMessages(['files' => "A submission may contain at most {$maximumFiles} files."]);
                }

                foreach ($files as $file) {
                    $path = $file->store("assignment-submissions/{$lockedSubmission->id}", $disk);

                    if ($path === false) {
                        throw new RuntimeException('A submission file could not be stored.');
                    }

                    $storedPaths[] = $path;
                    $lockedSubmission->files()->create($this->fileAttributes($file, $disk, $path));
                }

                return $this->submissions->loadSubmission($lockedSubmission);
            });
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk($disk)->delete($storedPaths);
            }

            throw $exception;
        }
    }

    public function deleteAttachment(AssignmentAttachment $attachment): void
    {
        DB::transaction(fn () => AssignmentAttachment::query()->lockForUpdate()->findOrFail($attachment->id)->delete());
        Storage::disk($attachment->storage_disk)->delete($attachment->storage_path);
    }

    public function deleteSubmissionFile(User $user, AssignmentSubmissionFile $file): AssignmentSubmission
    {
        $submission = DB::transaction(function () use ($user, $file): AssignmentSubmission {
            $lockedFile = AssignmentSubmissionFile::query()->lockForUpdate()->findOrFail($file->id);
            $lockedSubmission = AssignmentSubmission::query()->lockForUpdate()->findOrFail($lockedFile->assignment_submission_id);
            $this->submissions->authorizeOwner($user, $lockedSubmission);
            $this->submissions->ensureDraft($lockedSubmission);
            $this->access->requireAvailable($user, $lockedSubmission->assignment()->with('course')->firstOrFail());
            $lockedFile->delete();

            return $lockedSubmission;
        });

        Storage::disk($file->storage_disk)->delete($file->storage_path);

        return $this->submissions->loadSubmission($submission);
    }

    /** @return array{original_filename: string, storage_disk: string, storage_path: string, mime_type: string, file_size: int} */
    private function fileAttributes(UploadedFile $file, string $disk, string $path): array
    {
        $originalFilename = basename(str_replace('\\', '/', $file->getClientOriginalName()));

        return [
            'original_filename' => Str::limit($originalFilename, 255, ''),
            'storage_disk' => $disk,
            'storage_path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'file_size' => $file->getSize(),
        ];
    }
}
