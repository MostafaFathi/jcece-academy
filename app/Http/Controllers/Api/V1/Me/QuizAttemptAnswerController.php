<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Me\SaveQuizAnswersRequest;
use App\Http\Resources\Api\V1\StudentQuizAttemptResource;
use App\Models\QuizAttempt;
use App\Services\QuizAttemptService;
use Illuminate\Support\Facades\Gate;

class QuizAttemptAnswerController extends Controller
{
    public function __invoke(SaveQuizAnswersRequest $request, QuizAttempt $attempt, QuizAttemptService $attempts): StudentQuizAttemptResource
    {
        Gate::authorize('update', $attempt);

        return new StudentQuizAttemptResource($attempts->saveAnswers(
            $request->user(),
            $attempt,
            $request->validated('answers'),
        ));
    }
}
