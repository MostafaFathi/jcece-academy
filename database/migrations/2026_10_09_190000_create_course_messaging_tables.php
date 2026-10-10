<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('instructor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('kind', 16);
            $table->string('title', 120)->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
            $table->unique(['course_id', 'instructor_id', 'student_id'], 'course_private_identity');
            $table->index(['course_id', 'instructor_id', 'updated_at']);
        });
        Schema::create('course_conversation_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
            $table->unique(['course_conversation_id', 'user_id'], 'course_group_member_unique');
        });
        Schema::create('course_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->uuid('client_id');
            $table->text('body')->nullable();
            $table->foreignId('reply_to_id')->nullable()->constrained('course_messages')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['course_conversation_id', 'user_id', 'client_id'], 'course_message_retry_unique');
            $table->index(['course_conversation_id', 'id']);
        });
        Schema::create('course_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_message_id')->constrained()->cascadeOnDelete();
            $table->string('storage_path');
            $table->string('original_filename');
            $table->string('mime_type', 100);
            $table->string('kind', 16);
            $table->unsignedInteger('file_size');
            $table->decimal('duration_seconds', 7, 2)->nullable();
            $table->timestamps();
        });
        Schema::create('course_message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('emoji', 32);
            $table->timestamps();
            $table->unique(['course_message_id', 'user_id', 'emoji'], 'course_reaction_unique');
        });
        Schema::create('course_read_cursors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('last_read_message_id')->default(0);
            $table->timestamps();
            $table->unique(['course_conversation_id', 'user_id'], 'course_read_unique');
        });
        Schema::create('course_messaging_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_conversation_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('version');
            $table->string('kind', 32);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->timestamp('created_at');
            $table->unique(['course_conversation_id', 'version'], 'course_event_version_unique');
        });
    }

    public function down(): void
    {
        foreach (['course_messaging_events', 'course_read_cursors', 'course_message_reactions', 'course_message_attachments', 'course_messages', 'course_conversation_members', 'course_conversations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
