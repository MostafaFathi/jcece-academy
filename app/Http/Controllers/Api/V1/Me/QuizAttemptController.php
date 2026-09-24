<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudentQuizAttemptResource;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\QuizAccessService;
use App\Services\QuizAttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class QuizAttemptController extends Controller
{
    public function index(Request $request, Quiz $quiz, QuizAccessService $access): AnonymousResourceCollection
    {
        $access->requireAvailable($request->user(), $quiz->load('course'));
        $attempts = QuizAttempt::query()
            ->whereBelongsTo($quiz)
            ->whereBelongsTo($request->user())
            ->latest('started_at')
            ->get();

        return StudentQuizAttemptResource::collection($attempts);
    }

    public function store(Request $request, Quiz $quiz, QuizAttemptService $attempts): JsonResponse
    {
        Gate::authorize('create', QuizAttempt::class);

        return (new StudentQuizAttemptResource($attempts->start($request->user(), $quiz->load('course'))))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, QuizAttempt $attempt, QuizAttemptService $attempts): StudentQuizAttemptResource
    {
        Gate::authorize('view', $attempt);

        return new StudentQuizAttemptResource($attempts->refreshState($request->user(), $attempt));
    }
}
