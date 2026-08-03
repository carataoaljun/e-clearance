<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('student_id', 50);
            $table->string('personnel_id', 50);
            $table->string('office', 100);
            $table->string('approver_role', 50);
            $table->string('file_path', 500);
            $table->string('file_name', 255);
            $table->string('file_type', 100)->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['Pending', 'Received', 'Rejected'])->default('Pending');
            $table->text('remarks')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();

            $table->index('student_id', 'office_submissions_student_idx');
            $table->index('personnel_id', 'idx_personnel');

            $table->foreign('student_id')
                ->references('student_id')
                ->on('student_account')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_submissions');
    }
};
