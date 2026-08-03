<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_requirements', function (Blueprint $table) {
            $table->id();
            $table->string('student_id', 50);
            $table->string('office_role', 50);
            $table->string('requested_by', 50);
            $table->string('requirement', 255);
            $table->date('deadline')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['Pending', 'Submitted', 'Waived'])->default('Pending');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('student_id')
                ->references('student_id')
                ->on('student_account')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_requirements');
    }
};
