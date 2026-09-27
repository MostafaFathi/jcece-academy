<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\SupportTicketCategory;
use App\SupportTicketPriority;
use App\SupportTicketStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupportTicketRequest extends FormRequest
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
        return [
            'category' => ['sometimes', Rule::enum(SupportTicketCategory::class)],
            'priority' => ['sometimes', Rule::enum(SupportTicketPriority::class)],
            'status' => ['sometimes', Rule::enum(SupportTicketStatus::class)],
            'expected_updated_at' => ['sometimes', 'date'],
        ];
    }
}
