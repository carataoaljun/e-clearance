<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_assignment', function (Blueprint $table) {
            $table->id('assignment_id');
            $table->string('instructor_id', 50);
            $table->unsignedBigInteger('subject_id');
            $table->string('program', 5);
            $table->string('year_level', 20)->nullable();
            $table->string('section', 20)->nullable();
            $table->timestamp('assigned_at')->useCurrent();

            $table->foreign('instructor_id')
                ->references('instructor_id')
                ->on('instructor_account')
                ->onDelete('cascade');

            $table->foreign('subject_id')
                ->references('subject_id')
                ->on('subject_codes')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_assignment');
    }
};
