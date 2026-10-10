<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreCourseMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('messaging.send') ?? false;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'uuid'],
            'body' => ['nullable', 'string', 'max:10000', 'required_without:attachment'],
            'reply_to_id' => ['nullable', 'integer'],
            'attachment' => ['nullable', 'file', File::types(['jpg', 'jpeg', 'png', 'webp', 'webm', 'ogg', 'm4a', 'mp4', 'wav'])->max(10240)],
        ];
    }
}
