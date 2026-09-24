<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreQuizQuestionRequest;
use App\Http\Requests\Api\V1\Admin\UpdateQuizQuestionRequest;
use App\Http\Resources\Api\V1\AdminQuizQuestionResource;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Services\QuizQuestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class QuizQuestionController extends Controller
{
    public function index(Quiz $quiz): AnonymousResourceCollection
    {
        Gate::authorize('view', $quiz);

        return AdminQuizQuestionResource::collection($quiz->questions()->with('options')->get());
    }

    public function store(StoreQuizQuestionRequest $request, Quiz $quiz, QuizQuestionService $questions): JsonResponse
    {
        Gate::authorize('update', $quiz);
        $question = $questions->create($quiz, $request->validated());

        return (new AdminQuizQuestionResource($question))->response()->setStatusCode(201);
    }

    public function show(Quiz $quiz, QuizQuestion $question): AdminQuizQuestionResource
    {
        Gate::authorize('view', $quiz);

        return new AdminQuizQuestionResource($question->load('options'));
    }

    public function update(UpdateQuizQuestionRequest $request, Quiz $quiz, QuizQuestion $question, QuizQuestionService $questions): AdminQuizQuestionResource
    {
        Gate::authorize('update', $quiz);

        return new AdminQuizQuestionResource($questions->update($question, $request->validated()));
    }

    public function destroy(Quiz $quiz, QuizQuestion $question, QuizQuestionService $questions): Response
    {
        Gate::authorize('update', $quiz);
        $questions->delete($question);

        return response()->noContent();
    }
}
