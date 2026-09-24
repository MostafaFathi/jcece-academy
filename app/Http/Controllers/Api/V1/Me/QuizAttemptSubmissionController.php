<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudentQuizAttemptResource;
use App\Models\QuizAttempt;
use App\Services\QuizAttemptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class QuizAttemptSubmissionController extends Controller
{
    public function __invoke(Request $request, QuizAttempt $attempt, QuizAttemptService $attempts): StudentQuizAttemptResource
    {
        Gate::authorize('update', $attempt);

        return new StudentQuizAttemptResource($attempts->submit($request->user(), $attempt));
    }
}
