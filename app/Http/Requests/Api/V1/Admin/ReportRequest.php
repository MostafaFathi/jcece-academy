<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\PermissionName;
use App\RoleName;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor !== null && (
            ($this->is('api/v1/instructor/reports*') && $actor->hasRole(RoleName::Instructor->value) && $actor->can(PermissionName::CoursesView->value))
            || ($this->is('api/v1/admin/reports*') && $actor->can(PermissionName::ReportsView->value))
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'course_id' => ['nullable', 'integer', 'min:1', 'exists:courses,id'],
            'package_id' => ['nullable', 'integer', 'min:1', 'exists:packages,id'],
            'instructor_id' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'status' => ['nullable', 'string', 'in:pending_review,paid,rejected,pending,completed'],
            'method' => ['nullable', 'string', 'in:bank_transfer,wallet,manual'],
            'period' => ['nullable', 'string', 'in:day,month'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $type = (string) $this->route('type');
            $allowed = [
                'course_id' => ['courses', 'learners', 'reviews', 'quizzes'],
                'package_id' => ['packages'],
                'instructor_id' => ['courses', 'learners', 'instructors', 'reviews', 'quizzes'],
                'currency' => ['sales', 'payments', 'refunds', 'packages', 'coupons'],
                'status' => ['payments', 'refunds'],
                'method' => ['payments'],
                'period' => ['sales'],
            ];
            foreach ($allowed as $field => $types) {
                if ($this->filled($field) && ! in_array($type, $types, true)) {
                    $validator->errors()->add($field, 'This filter is not available for the selected report.');
                }
            }
            if ($this->is('api/v1/instructor/reports*') && $this->filled('instructor_id')) {
                $validator->errors()->add('instructor_id', 'Instructor reports are limited to your own courses.');
            }
            $from = CarbonImmutable::parse($this->input('from', now('Asia/Hebron')->subDays(29)->toDateString()), 'Asia/Hebron');
            $to = CarbonImmutable::parse($this->input('to', now('Asia/Hebron')->toDateString()), 'Asia/Hebron');
            if ($to->lt($from) || $from->diffInDays($to) > 366) {
                $validator->errors()->add('to', 'The reporting range must be chronological and at most 366 days.');
            }
        }];
    }
}
