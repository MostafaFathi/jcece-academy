<?php

namespace App\Services;

use App\Contracts\CertificatePdfGenerator;
use App\Models\Certificate;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class MpdfCertificatePdfGenerator implements CertificatePdfGenerator
{
    public function generate(Certificate $certificate, string $verificationUrl): string
    {
        $temporaryDirectory = storage_path('app/mpdf');

        File::ensureDirectoryExists($temporaryDirectory);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'tempDir' => $temporaryDirectory,
            'default_font' => 'dejavusans',
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
        ]);
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->WriteHTML(view('certificates.pdf', [
            'certificate' => $certificate,
            'verificationUrl' => $verificationUrl,
            'logoPath' => (string) config('jcec.certificates.logo_path'),
        ])->render());

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
