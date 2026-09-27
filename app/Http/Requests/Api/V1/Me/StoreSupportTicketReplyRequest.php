<?php

namespace App\Http\Requests\Api\V1\Me;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreSupportTicketReplyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maximumCount = (int) config('jcec.support.attachment_max_count', 5);

        return [
            'body' => ['required', 'string', 'max:10000'],
            'attachments' => ['sometimes', 'array', "max:{$maximumCount}"],
            'attachments.*' => ['file', File::types(config('jcec.support.attachment_mimes'))->max((int) config('jcec.support.attachment_max_kilobytes'))],
        ];
    }
}
