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
        Schema::table('courses', function (Blueprint $table) {
            $table->decimal('certificate_required_lesson_percentage', 5, 2)->nullable();
            $table->boolean('certificate_final_exam_required')->default(false);
            $table->foreignId('certificate_final_exam_quiz_id')->nullable();
            $table->foreign('certificate_final_exam_quiz_id', 'courses_certificate_final_quiz_fk')->references('id')->on('quizzes')->nullOnDelete();
            $table->decimal('certificate_final_exam_passing_percentage', 5, 2)->nullable();
            $table->boolean('certificate_admin_approval_required')->default(false);
            $table->unsignedInteger('certificate_requirements_version')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign('courses_certificate_final_quiz_fk');
            $table->dropColumn([
                'certificate_final_exam_quiz_id',
                'certificate_required_lesson_percentage',
                'certificate_final_exam_required',
                'certificate_final_exam_passing_percentage',
                'certificate_admin_approval_required',
                'certificate_requirements_version',
            ]);
        });
    }
};
