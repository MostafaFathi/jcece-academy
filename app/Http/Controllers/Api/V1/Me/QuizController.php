<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudentQuizResource;
use App\Models\Quiz;
use App\QuizStatus;
use App\Services\QuizAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuizController extends Controller
{
    public function index(Request $request, QuizAccessService $access): AnonymousResourceCollection
    {
        $quizzes = Quiz::query()
            ->where('status', QuizStatus::Published)
            ->where(fn ($query) => $query->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->where(fn ($query) => $query->whereNull('available_until')->orWhere('available_until', '>=', now()))
            ->with('course')
            ->orderBy('course_id')
            ->orderBy('id')
            ->get()
            ->filter(fn (Quiz $quiz): bool => $access->isAvailableTo($request->user(), $quiz))
            ->values();

        return StudentQuizResource::collection($quizzes);
    }

    public function show(Request $request, Quiz $quiz, QuizAccessService $access): StudentQuizResource
    {
        $access->requireAvailable($request->user(), $quiz->load('course'));

        return new StudentQuizResource($quiz);
    }
}
