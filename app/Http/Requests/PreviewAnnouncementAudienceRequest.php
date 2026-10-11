<?php

namespace App\Http\Requests;

class PreviewAnnouncementAudienceRequest extends SaveAnnouncementRequest
{
    public function rules(): array
    {
        return array_filter(parent::rules(), fn (string $key): bool => $key === 'audience' || str_starts_with($key, 'audience.'), ARRAY_FILTER_USE_KEY);
    }
}
