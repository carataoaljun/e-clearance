<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_remarks', function (Blueprint $table) {
            $table->id();
            $table->string('student_id', 50);
            $table->unsignedBigInteger('subject_id');
            $table->string('instructor_id', 50);
            $table->text('remark');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_id', 'subject_id', 'instructor_id'], 'idx_remark_target');

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
        Schema::dropIfExists('instructor_remarks');
    }
};
