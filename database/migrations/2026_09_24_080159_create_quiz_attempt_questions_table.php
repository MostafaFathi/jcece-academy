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
        Schema::create('quiz_attempt_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_question_id')->nullable()->constrained('quiz_questions')->nullOnDelete();
            $table->string('type', 32);
            $table->text('question_text');
            $table->text('explanation')->nullable();
            $table->decimal('points', 8, 2);
            $table->integer('sort_order');
            $table->timestamps();

            $table->unique(['quiz_attempt_id', 'sort_order']);
            $table->index(['quiz_attempt_id', 'source_question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_attempt_questions');
    }
};
