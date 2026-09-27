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
        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('status', 32)->default('draft');
            $table->string('active_key')->nullable()->unique();
            $table->longText('text_answer')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('is_late')->default(false);
            $table->string('assignment_title');
            $table->longText('assignment_instructions')->nullable();
            $table->decimal('maximum_score', 10, 2);
            $table->decimal('passing_score', 10, 2)->nullable();
            $table->string('submission_type', 32);
            $table->timestamp('due_at')->nullable();
            $table->boolean('allow_late_submissions');
            $table->decimal('score', 10, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['assignment_id', 'user_id', 'attempt_number'], 'assignment_user_attempt_unique');
            $table->index(['assignment_id', 'status', 'submitted_at'], 'assignment_status_submitted_index');
            $table->index(['user_id', 'assignment_id', 'created_at'], 'assignment_user_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
    }
};
