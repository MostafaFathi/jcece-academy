<?php

namespace App\Services;

use App\Models\FinancialDocument;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class MpdfFinancialDocumentGenerator
{
    public function generate(FinancialDocument $document): string
    {
        $temporaryDirectory = storage_path('app/mpdf');
        File::ensureDirectoryExists($temporaryDirectory);

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $temporaryDirectory,
            'default_font' => 'dejavusans',
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_top' => 14,
            'margin_bottom' => 14,
        ]);
        $pdf->autoScriptToLang = true;
        $pdf->autoLangToFont = true;
        $pdf->WriteHTML(view('financial-documents.pdf', [
            'document' => $document,
            'snapshot' => $document->snapshot,
            'logoPath' => (string) config('jcec.certificates.logo_path'),
        ])->render());

        return $pdf->Output('', Destination::STRING_RETURN);
    }
}
