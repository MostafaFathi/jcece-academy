<?php

namespace App\Services;

use App\Models\Quiz;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuizQuestionOrderService
{
    /** @param list<int> $ids */
    public function reorder(Quiz $quiz, array $ids): void
    {
        DB::transaction(function () use ($quiz, $ids): void {
            $lockedQuiz = Quiz::query()->lockForUpdate()->findOrFail($quiz->id);
            $ownedIds = $lockedQuiz->questions()->lockForUpdate()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
            $expectedIds = $ids;
            sort($ownedIds);
            sort($expectedIds);

            if ($ownedIds !== $expectedIds) {
                throw ValidationException::withMessages([
                    'ids' => 'The supplied IDs must exactly match the questions owned by this quiz.',
                ]);
            }

            foreach ($ids as $sortOrder => $id) {
                $lockedQuiz->questions()->whereKey($id)->update(['sort_order' => $sortOrder]);
            }
        });
    }
}
