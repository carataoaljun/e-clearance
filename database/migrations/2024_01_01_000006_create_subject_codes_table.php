<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_codes', function (Blueprint $table) {
            $table->id('subject_id');
            $table->string('subject_code', 30)->unique();
            $table->text('subject_description')->nullable();
            $table->string('year_level', 10);
            $table->string('program', 50);
            $table->string('semester', 50);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_codes');
    }
};
