<?php

namespace App\Contracts;

use App\Models\Certificate;

interface CertificatePdfGenerator
{
    public function generate(Certificate $certificate, string $verificationUrl): string;
}
