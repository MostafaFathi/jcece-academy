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
        Schema::create('course_review_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_review_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name_snapshot');
            $table->string('action', 32);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->unsignedTinyInteger('old_rating')->nullable();
            $table->unsignedTinyInteger('new_rating')->nullable();
            $table->string('old_title')->nullable();
            $table->string('new_title')->nullable();
            $table->text('old_body')->nullable();
            $table->text('new_body')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at');

            $table->index(['course_review_id', 'created_at'], 'review_history_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_review_histories');
    }
};
