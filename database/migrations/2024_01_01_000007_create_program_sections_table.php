<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_sections', function (Blueprint $table) {
            $table->id();
            $table->string('program', 50);
            $table->string('year_level', 20);
            $table->string('section', 50);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['program', 'year_level', 'section'], 'unique_section');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_sections');
    }
};
