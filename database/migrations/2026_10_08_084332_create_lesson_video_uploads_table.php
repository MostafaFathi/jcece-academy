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
        Schema::create('lesson_video_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->uuid('request_id');
            $table->uuid('video_guid')->nullable()->unique();
            $table->string('status', 32)->default('creating');
            $table->string('filename', 255);
            $table->string('mime_type', 64);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedTinyInteger('encode_progress')->default(0);
            $table->boolean('is_current')->default(false);
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['lesson_id', 'request_id'], 'lesson_video_request_unique');
            $table->index(['lesson_id', 'status'], 'lesson_video_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_video_uploads');
    }
};
