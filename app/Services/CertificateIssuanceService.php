<?php

namespace App\Services;

use App\CertificateStatus;
use App\Contracts\CertificatePdfGenerator;
use App\LessonProgressStatus;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CertificateIssuanceService
{
    public function __construct(
        public CertificateEligibilityService $eligibility,
        public CertificatePdfGenerator $pdfGenerator,
        private AuditTrail $audit,
        private TransactionalDeliveryService $deliveries,
    ) {}

    public function issue(User $user, Course $course, bool $isExplicitReissue = false, ?User $actor = null): Certificate
    {
        $storedFile = null;

        try {
            return DB::transaction(function () use ($user, $course, $isExplicitReissue, $actor, &$storedFile): Certificate {
                $course = Course::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();
                $enrollment = Enrollment::query()
                    ->whereBelongsTo($user)
                    ->whereBelongsTo($course)
                    ->lockForUpdate()
                    ->first();

                if ($enrollment === null) {
                    throw ValidationException::withMessages(['course' => 'A valid course enrollment is required.']);
                }

                $activeCertificate = Certificate::query()
                    ->where('active_key', $this->activeKey($user, $course))
                    ->lockForUpdate()
                    ->first();

                if ($activeCertificate !== null) {
                    return $activeCertificate;
                }

                $hasRevokedCertificate = Certificate::query()
                    ->whereBelongsTo($user)
                    ->whereBelongsTo($course)
                    ->where('status', CertificateStatus::Revoked)
                    ->exists();

                if ($hasRevokedCertificate && ! $isExplicitReissue) {
                    throw ValidationException::withMessages([
                        'certificate' => 'A revoked certificate requires an explicit administrative reissue.',
                    ]);
                }

                if ($isExplicitReissue && ! $hasRevokedCertificate) {
                    throw ValidationException::withMessages([
                        'certificate' => 'Reissue requires a previously revoked certificate.',
                    ]);
                }

                $eligibility = $this->eligibility->evaluate($user, $course);

                if (! $eligibility['eligible']) {
                    throw ValidationException::withMessages(['course' => $eligibility['reasons']]);
                }

                $course->loadMissing('instructor');
                $issuedAt = now();
                $certificate = new Certificate([
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                    'enrollment_id' => $enrollment->id,
                    'certificate_number' => $this->certificateNumber($issuedAt->year),
                    'verification_token' => Str::random(64),
                    'active_key' => $this->activeKey($user, $course),
                    'student_name_snapshot' => $user->name,
                    'course_title_snapshot' => $course->title,
                    'instructor_name_snapshot' => $course->instructor?->name,
                    'issued_at' => $issuedAt,
                    'completed_at' => $this->completionDate($enrollment),
                    'status' => CertificateStatus::Issued,
                ]);
                $verificationUrl = route('certificates.verify.page', ['token' => $certificate->verification_token]);
                $pdf = $this->pdfGenerator->generate($certificate, $verificationUrl);
                $disk = (string) config('jcec.certificates.pdf_disk', 'local');
                $path = "certificates/{$certificate->certificate_number}.pdf";

                if (! Storage::disk($disk)->put($path, $pdf)) {
                    throw new \RuntimeException('The certificate PDF could not be stored.');
                }

                $storedFile = ['disk' => $disk, 'path' => $path];
                $certificate->pdf_disk = $disk;
                $certificate->pdf_path = $path;
                $certificate->save();

                $this->audit->record($isExplicitReissue ? 'certificate.reissued' : 'certificate.issued', $certificate, $actor ?? $user);
                $this->deliveries->recordForUser('certificate_issued', 'Certificate', $certificate->id, $user);

                return $certificate->refresh();
            });
        } catch (Throwable $exception) {
            if ($storedFile !== null) {
                Storage::disk($storedFile['disk'])->delete($storedFile['path']);
            }

            throw $exception;
        }
    }

    private function activeKey(User $user, Course $course): string
    {
        return "{$user->id}:{$course->id}";
    }

    private function certificateNumber(int $year): string
    {
        return 'JCEC-'.$year.'-'.Str::upper((string) Str::ulid());
    }

    private function completionDate(Enrollment $enrollment): CarbonInterface
    {
        if ($enrollment->completed_at !== null) {
            return $enrollment->completed_at;
        }

        $latestCompletedAt = $enrollment->lessonProgress()
            ->where('status', LessonProgressStatus::Completed)
            ->max('completed_at');

        return $latestCompletedAt === null ? now() : Date::parse($latestCompletedAt);
    }
}
