<?php

namespace App\Services;

use App\Contracts\ProtectedVideoProvider;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class UnconfiguredProtectedVideoProvider implements ProtectedVideoProvider
{
    /** @return array{url: string, expires_at: CarbonImmutable} */
    public function issuePlayback(string $assetKey, CarbonImmutable $expiresAt): array
    {
        throw new ServiceUnavailableHttpException(null, 'Protected video provider is not configured.');
    }
}
