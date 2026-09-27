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
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('instructions')->nullable();
            $table->string('status', 32)->default('draft');
            $table->string('submission_type', 32);
            $table->decimal('maximum_score', 10, 2);
            $table->decimal('passing_score', 10, 2)->nullable();
            $table->unsignedInteger('max_attempts')->nullable();
            $table->timestamp('available_from')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->boolean('allow_late_submissions')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_id', 'status', 'available_from']);
            $table->index(['lesson_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
