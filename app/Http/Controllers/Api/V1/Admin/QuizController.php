<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreQuizRequest;
use App\Http\Requests\Api\V1\Admin\UpdateQuizRequest;
use App\Http\Resources\Api\V1\AdminQuizResource;
use App\Models\Course;
use App\Models\Quiz;
use App\QuizStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class QuizController extends Controller
{
    public function index(Course $course): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Quiz::class);

        return AdminQuizResource::collection($course->quizzes()->with('questions.options')->get());
    }

    public function store(StoreQuizRequest $request, Course $course): JsonResponse
    {
        Gate::authorize('create', Quiz::class);
        $quiz = $course->quizzes()->create($request->validated() + ['status' => QuizStatus::Draft]);

        return (new AdminQuizResource($quiz))->response()->setStatusCode(201);
    }

    public function show(Course $course, Quiz $quiz): AdminQuizResource
    {
        Gate::authorize('view', $quiz);

        return new AdminQuizResource($quiz->load('questions.options'));
    }

    public function update(UpdateQuizRequest $request, Course $course, Quiz $quiz): AdminQuizResource
    {
        Gate::authorize('update', $quiz);
        $quiz->update($request->validated());

        return new AdminQuizResource($quiz->refresh()->load('questions.options'));
    }

    public function destroy(Course $course, Quiz $quiz): AdminQuizResource
    {
        Gate::authorize('delete', $quiz);
        $quiz->update(['status' => QuizStatus::Archived]);

        return new AdminQuizResource($quiz->refresh()->load('questions.options'));
    }
}
