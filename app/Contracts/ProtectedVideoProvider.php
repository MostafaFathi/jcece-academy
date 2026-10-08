<?php

namespace App\Contracts;

use Carbon\CarbonImmutable;

interface ProtectedVideoProvider
{
    /** @return array{url: string, expires_at: CarbonImmutable} */
    public function issuePlayback(string $assetKey, CarbonImmutable $expiresAt): array;
}
