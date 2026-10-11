<?php

namespace App\Http\Requests;

use App\Models\Course;
use App\RoleName;
use App\UserStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === UserStatus::Active && $this->user()->can('announcements.manage');
    }

    public function rules(): array
    {
        return ['title' => ['required', 'array:ar,en'], 'title.ar' => ['required', 'string', 'max:255'], 'title.en' => ['required', 'string', 'max:255'], 'body' => ['required', 'array:ar,en'], 'body.ar' => ['required', 'string', 'max:10000'], 'body.en' => ['required', 'string', 'max:10000'], 'audience' => ['required', 'array:kind,roles,course_ids'], 'audience.kind' => ['required', Rule::in(['all', 'roles', 'course_students', 'course_instructors'])], 'audience.roles' => ['required_if:audience.kind,roles', 'array', 'max:5'], 'audience.roles.*' => [Rule::in(array_column(RoleName::cases(), 'value'))], 'audience.course_ids' => ['required_if:audience.kind,course_students,course_instructors', 'array', 'min:1', 'max:100'], 'audience.course_ids.*' => ['integer', 'distinct', 'exists:courses,id'], 'publish_at' => ['nullable', 'date', 'after:now'], 'expires_at' => ['nullable', 'date', 'after:now']];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            foreach ($this->input('audience.course_ids', []) as $id) {
                $course = Course::query()->find($id);
                if (! $course || ! Gate::forUser($this->user())->allows('view', $course)) {
                    $validator->errors()->add('audience.course_ids', 'Course is outside your authorized scope.');
                }
            }
            if ($this->input('publish_at') && (! $this->user()->can('announcements.publish'))) {
                $validator->errors()->add('publish_at', 'Publication permission is required.');
            }
            if ($this->input('publish_at') && $this->input('expires_at') && CarbonImmutable::parse($this->input('expires_at'))->lte(CarbonImmutable::parse($this->input('publish_at')))) {
                $validator->errors()->add('expires_at', 'Expiration must follow publication.');
            }
        }];
    }
}
