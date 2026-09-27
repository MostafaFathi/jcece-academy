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
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->string('certificate_number', 64)->unique();
            $table->string('verification_token', 128)->unique();
            $table->string('active_key')->nullable()->unique();
            $table->string('student_name_snapshot');
            $table->string('course_title_snapshot');
            $table->string('instructor_name_snapshot')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('completed_at');
            $table->string('status', 32);
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revocation_reason')->nullable();
            $table->string('pdf_disk', 64)->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'issued_at'], 'certificate_user_issued_index');
            $table->index(['course_id', 'status', 'issued_at'], 'certificate_course_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
