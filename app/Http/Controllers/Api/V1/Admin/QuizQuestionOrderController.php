<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReorderQuizQuestionsRequest;
use App\Models\Quiz;
use App\Services\QuizQuestionOrderService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class QuizQuestionOrderController extends Controller
{
    public function __invoke(ReorderQuizQuestionsRequest $request, Quiz $quiz, QuizQuestionOrderService $order): Response
    {
        Gate::authorize('update', $quiz);
        $order->reorder($quiz, $request->validated('ids'));

        return response()->noContent();
    }
}
