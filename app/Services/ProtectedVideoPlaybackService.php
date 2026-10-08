<?php

namespace App\Services;

use App\Contracts\ProtectedVideoProvider;
use App\Models\Lesson;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class ProtectedVideoPlaybackService
{
    public function __construct(private ProtectedVideoProvider $provider) {}

    /** @return array{url: string, expires_at: string, player: string} */
    public function playback(Lesson $lesson): array
    {
        if ($lesson->is_preview || blank($lesson->protected_video_asset_key)) {
            throw ValidationException::withMessages(['video' => 'Protected playback is unavailable for this lesson.']);
        }

        $bunny = $lesson->video_provider === 'bunny_stream';

        if (! $bunny && config('jcec.bunny_stream.enabled')) {
            throw new ServiceUnavailableHttpException(null, 'Legacy protected video provider is not configured.');
        }

        if ($bunny && ! $lesson->videoUploads()->where('video_guid', $lesson->protected_video_asset_key)->where('status', 'ready')->where('is_current', true)->exists()) {
            throw ValidationException::withMessages(['video' => 'Protected playback is not ready for this lesson.']);
        }

        $ttl = $bunny ? (int) config('jcec.bunny_stream.playback_ttl_seconds') : 120;
        $expiresAt = CarbonImmutable::now()->addSeconds($ttl);
        $issued = $this->provider->issuePlayback($lesson->protected_video_asset_key, $expiresAt);
        $url = $issued['url'];
        $host = parse_url($url, PHP_URL_HOST);
        $allowedHosts = $bunny ? ['player.mediadelivery.net'] : config('jcec.protected_video.allowed_playback_hosts', []);

        if (! is_string($host) || parse_url($url, PHP_URL_SCHEME) !== 'https'
            || ! in_array(strtolower($host), $allowedHosts, true)
            || $issued['expires_at']->isPast()
            || $issued['expires_at']->greaterThan($expiresAt)) {
            throw new \UnexpectedValueException('Protected video provider returned an unsafe playback source.');
        }

        return ['url' => $url, 'expires_at' => $issued['expires_at']->toIso8601String(), 'player' => $bunny ? 'bunny_embed' : 'html5'];
    }
}
