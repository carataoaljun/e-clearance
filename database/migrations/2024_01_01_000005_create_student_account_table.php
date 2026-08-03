<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_account', function (Blueprint $table) {
            $table->id();
            $table->string('student_id', 50)->unique();
            $table->string('firstname', 100);
            $table->string('middlename', 100)->nullable();
            $table->string('lastname', 100);
            $table->string('suffix', 10)->nullable();
            $table->string('email', 100)->unique();
            $table->string('password', 255);
            $table->string('program', 100);
            $table->string('year_level', 20);
            $table->string('section', 20);
            $table->enum('student_type', ['Regular', 'Irregular'])->default('Regular');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_account');
    }
};
