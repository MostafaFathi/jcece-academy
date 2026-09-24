<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AdminQuizResource;
use App\Models\Quiz;
use App\Services\QuizPublicationService;
use Illuminate\Support\Facades\Gate;

class QuizPublicationController extends Controller
{
    public function store(Quiz $quiz, QuizPublicationService $publication): AdminQuizResource
    {
        Gate::authorize('publish', $quiz);

        return new AdminQuizResource($publication->publish($quiz));
    }

    public function destroy(Quiz $quiz, QuizPublicationService $publication): AdminQuizResource
    {
        Gate::authorize('publish', $quiz);

        return new AdminQuizResource($publication->unpublish($quiz));
    }
}
