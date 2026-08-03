<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subject_codes', function (Blueprint $table) {
            $table->dropUnique('subject_codes_subject_code_unique');
            $table->unique(
                ['subject_code', 'year_level', 'program', 'semester'],
                'subject_codes_academic_assignment_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('subject_codes', function (Blueprint $table) {
            $table->dropUnique('subject_codes_academic_assignment_unique');
            $table->unique('subject_code');
        });
    }
};
