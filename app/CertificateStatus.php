<?php

namespace App;

enum CertificateStatus: string
{
    case Issued = 'issued';
    case Revoked = 'revoked';
}
