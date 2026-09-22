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
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_section_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('type');
            $table->text('description')->nullable();
            $table->longText('content')->nullable();
            $table->string('video_provider')->nullable();
            $table->string('video_id')->nullable();
            $table->string('video_url')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('is_preview')->default(false);
            $table->boolean('is_published')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['course_section_id', 'slug']);
            $table->index(['course_section_id', 'sort_order']);
            $table->index(['course_section_id', 'is_published', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
