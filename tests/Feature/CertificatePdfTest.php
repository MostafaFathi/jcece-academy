<?php

namespace Tests\Feature;

use App\CertificateStatus;
use App\Models\Certificate;
use App\Services\MpdfCertificatePdfGenerator;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class CertificatePdfTest extends TestCase
{
    public function test_pdf_generator_embeds_arabic_and_english_content_with_qr(): void
    {
        $certificate = new Certificate([
            'certificate_number' => 'JCEC-2026-ARABIC123',
            'verification_token' => str_repeat('a', 64),
            'student_name_snapshot' => 'أحمد محمد الخطيب',
            'course_title_snapshot' => 'إدارة المشاريع الاحترافية',
            'instructor_name_snapshot' => 'المهندسة سارة علي',
            'completed_at' => Date::parse('2026-09-20'),
            'issued_at' => Date::parse('2026-09-27'),
            'status' => CertificateStatus::Issued,
        ]);

        $pdf = app(MpdfCertificatePdfGenerator::class)->generate(
            $certificate,
            'https://academy.example.test/api/v1/certificates/verify/'.str_repeat('a', 64),
        );

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(50_000, strlen($pdf));
        $this->assertStringContainsString('/Type /Page', $pdf);
    }
}
