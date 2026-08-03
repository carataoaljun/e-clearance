<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tracks individual clearance status per non-program-head office per student
        Schema::create('office_clearance_status', function (Blueprint $table) {
            $table->id();
            $table->string('student_id', 50);
            $table->string('office_role', 50);
            $table->string('approver_id', 50);
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->text('remarks')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['student_id', 'office_role'], 'unique_office_student');

            $table->foreign('student_id')
                ->references('student_id')
                ->on('student_account')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_clearance_status');
    }
};
