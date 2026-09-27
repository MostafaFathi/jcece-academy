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
        Schema::create('assignment_submission_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_submission_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('storage_disk', 64);
            $table->string('storage_path');
            $table->string('mime_type', 255);
            $table->unsignedBigInteger('file_size');
            $table->timestamps();

            $table->index(['assignment_submission_id', 'created_at'], 'submission_file_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_submission_files');
    }
};
