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
        Schema::create('assignment_grading_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->decimal('previous_score', 10, 2)->nullable();
            $table->boolean('previous_passed')->nullable();
            $table->text('previous_feedback')->nullable();
            $table->decimal('score', 10, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->text('feedback')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['assignment_submission_id', 'created_at'], 'grading_event_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_grading_events');
    }
};
