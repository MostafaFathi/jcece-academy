<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateDownloadController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Certificate $certificate): StreamedResponse
    {
        Gate::authorize('download', $certificate);
        abort_if($certificate->pdf_disk === null || $certificate->pdf_path === null, 404);
        $disk = Storage::disk($certificate->pdf_disk);
        abort_unless($disk->exists($certificate->pdf_path), 404);

        return $disk->download($certificate->pdf_path, "{$certificate->certificate_number}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
