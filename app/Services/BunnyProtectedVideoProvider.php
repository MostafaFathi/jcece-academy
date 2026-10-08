<?php

namespace App\Services;

use App\Contracts\ProtectedVideoProvider;
use Carbon\CarbonImmutable;

class BunnyProtectedVideoProvider implements ProtectedVideoProvider
{
    public function __construct(private BunnyStreamClient $client) {}

    /** @return array{url: string, expires_at: CarbonImmutable} */
    public function issuePlayback(string $assetKey, CarbonImmutable $expiresAt): array
    {
        $this->client->requireConfigured();

        if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $assetKey) !== 1) {
            throw new \InvalidArgumentException('Invalid Bunny video identifier.');
        }

        $expiration = $expiresAt->timestamp;
        $libraryId = config('jcec.bunny_stream.library_id');
        $token = hash('sha256', config('jcec.bunny_stream.token_key').$assetKey.$expiration);
        $url = "https://player.mediadelivery.net/embed/{$libraryId}/{$assetKey}?token={$token}&expires={$expiration}";

        return ['url' => $url, 'expires_at' => $expiresAt];
    }
}
