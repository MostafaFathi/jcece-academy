<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class BunnyStreamClient
{
    public function configured(): bool
    {
        $configuration = config('jcec.bunny_stream');

        return $configuration['enabled'] === true
            && preg_match('/^[1-9][0-9]*$/', $configuration['library_id']) === 1
            && filled($configuration['api_key'])
            && filled($configuration['token_key'])
            && preg_match('/^[a-z0-9.-]+\.b-cdn\.net$/', $configuration['cdn_host']) === 1
            && $configuration['api_endpoint'] === 'https://video.bunnycdn.com'
            && $configuration['playback_ttl_seconds'] >= 30
            && $configuration['playback_ttl_seconds'] <= 300
            && $configuration['upload_signature_ttl_seconds'] >= 3600
            && $configuration['upload_signature_ttl_seconds'] <= 86400
            && $configuration['max_upload_megabytes'] >= 1
            && $configuration['max_upload_megabytes'] <= 20480;
    }

    public function requireConfigured(): void
    {
        if (! $this->configured()) {
            throw new ServiceUnavailableHttpException(null, 'Protected video provider is not configured.');
        }
    }

    public function create(string $title): string
    {
        $this->requireConfigured();

        try {
            $response = $this->request()->post($this->videoCollectionUrl(), ['title' => $title]);
        } catch (ConnectionException) {
            throw new ServiceUnavailableHttpException(null, 'Video provider is temporarily unavailable.');
        }

        $guid = $response->json('guid');

        if (! $response->successful() || ! is_string($guid) || ! $this->validGuid($guid)) {
            throw new ServiceUnavailableHttpException(null, 'Video provider could not create the upload.');
        }

        return strtolower($guid);
    }

    /** @return array{status: int, encode_progress: int, length: int} */
    public function status(string $guid): array
    {
        $this->requireConfigured();
        $this->assertGuid($guid);

        try {
            $response = $this->request()->get($this->videoCollectionUrl().'/'.$guid);
        } catch (ConnectionException) {
            throw new ServiceUnavailableHttpException(null, 'Video provider is temporarily unavailable.');
        }

        if (! $response->successful() || ! is_int($response->json('status'))) {
            throw new ServiceUnavailableHttpException(null, 'Video provider status is unavailable.');
        }

        return [
            'status' => $response->json('status'),
            'encode_progress' => max(0, min(100, (int) $response->json('encodeProgress', 0))),
            'length' => max(0, (int) $response->json('length', 0)),
        ];
    }

    public function delete(string $guid): void
    {
        $this->requireConfigured();
        $this->assertGuid($guid);

        try {
            $response = $this->request()->delete($this->videoCollectionUrl().'/'.$guid);
        } catch (ConnectionException) {
            throw new ServiceUnavailableHttpException(null, 'Video provider is temporarily unavailable.');
        }

        if (! $response->successful() && $response->status() !== 404) {
            throw new ServiceUnavailableHttpException(null, 'Video provider could not remove the video.');
        }
    }

    /** @return array{endpoint: string, library_id: string, video_id: string, authorization_signature: string, authorization_expire: int} */
    public function uploadAuthorization(string $guid): array
    {
        $this->requireConfigured();
        $this->assertGuid($guid);
        $expiration = now()->addSeconds(config('jcec.bunny_stream.upload_signature_ttl_seconds'))->timestamp;
        $libraryId = config('jcec.bunny_stream.library_id');

        return [
            'endpoint' => 'https://video.bunnycdn.com/tusupload',
            'library_id' => $libraryId,
            'video_id' => $guid,
            'authorization_signature' => hash('sha256', $libraryId.config('jcec.bunny_stream.api_key').$expiration.$guid),
            'authorization_expire' => $expiration,
        ];
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders(['AccessKey' => config('jcec.bunny_stream.api_key')])
            ->acceptJson()->connectTimeout(3)->timeout(10);
    }

    private function videoCollectionUrl(): string
    {
        return config('jcec.bunny_stream.api_endpoint').'/library/'.config('jcec.bunny_stream.library_id').'/videos';
    }

    private function assertGuid(string $guid): void
    {
        if (! $this->validGuid($guid)) {
            throw new \InvalidArgumentException('Invalid video identifier.');
        }
    }

    private function validGuid(string $guid): bool
    {
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $guid) === 1;
    }
}
