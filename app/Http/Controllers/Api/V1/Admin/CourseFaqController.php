<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseFaq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CourseFaqController extends Controller
{
    public function index(Course $course): JsonResponse
    {
        Gate::authorize('view', $course);

        return response()->json(['data' => $course->faqs()->get()]);
    }

    public function store(Request $request, Course $course): JsonResponse
    {
        Gate::authorize('update', $course);
        $faq = $course->faqs()->create($this->attributes($request, true));

        return response()->json(['data' => $faq], 201);
    }

    public function update(Request $request, Course $course, CourseFaq $faq): JsonResponse
    {
        Gate::authorize('update', $course);
        abort_unless($faq->course_id === $course->id, 404);
        $faq->update($this->attributes($request, false));

        return response()->json(['data' => $faq->refresh()]);
    }

    public function destroy(Course $course, CourseFaq $faq): JsonResponse
    {
        Gate::authorize('update', $course);
        abort_unless($faq->course_id === $course->id, 404);
        $faq->delete();

        return response()->json(status: 204);
    }

    /** @return array<string, mixed> */
    private function attributes(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'question_ar' => [$required, 'string', 'max:1000'],
            'answer_ar' => [$required, 'string', 'max:5000'],
            'question_en' => [$required, 'string', 'max:1000'],
            'answer_en' => [$required, 'string', 'max:5000'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
