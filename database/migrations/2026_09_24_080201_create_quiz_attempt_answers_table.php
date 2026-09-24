<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quiz_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_attempt_question_id')->constrained()->cascadeOnDelete();
            $table->json('selected_option_ids');
            $table->decimal('earned_points', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['quiz_attempt_id', 'quiz_attempt_question_id'], 'quiz_attempt_answers_attempt_question_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_attempt_answers');
    }
};
