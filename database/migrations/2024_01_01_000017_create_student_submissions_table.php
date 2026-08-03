<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('student_id', 50);
            $table->unsignedBigInteger('subject_id');
            $table->string('instructor_id', 50);
            $table->string('file_path', 500);
            $table->string('file_name', 255);
            $table->string('file_type', 100)->nullable();
            $table->text('description')->nullable();
            $table->timestamp('submitted_at')->useCurrent();

            $table->index(['student_id', 'subject_id', 'instructor_id'], 'idx_student_subject');

            $table->foreign('student_id')
                ->references('student_id')
                ->on('student_account')
                ->onDelete('cascade');

            $table->foreign('subject_id')
                ->references('subject_id')
                ->on('subject_codes')
                ->onDelete('cascade');

            $table->foreign('instructor_id')
                ->references('instructor_id')
                ->on('instructor_account')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_submissions');
    }
};
