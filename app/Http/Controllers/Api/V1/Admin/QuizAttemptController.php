<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AdminQuizAttemptResource;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class QuizAttemptController extends Controller
{
    public function index(Quiz $quiz): AnonymousResourceCollection
    {
        Gate::authorize('viewResults', $quiz);

        return AdminQuizAttemptResource::collection($quiz->attempts()->paginate(25));
    }

    public function show(Quiz $quiz, QuizAttempt $attempt): AdminQuizAttemptResource
    {
        Gate::authorize('viewResults', $quiz);

        return new AdminQuizAttemptResource($attempt->load(['questions.options', 'questions.answer']));
    }
}
