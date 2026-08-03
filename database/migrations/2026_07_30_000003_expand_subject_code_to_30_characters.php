<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subject_codes', function (Blueprint $table) {
            $table->string('subject_code', 30)->change();
        });
    }

    public function down(): void
    {
        Schema::table('subject_codes', function (Blueprint $table) {
            $table->string('subject_code', 20)->change();
        });
    }
};
