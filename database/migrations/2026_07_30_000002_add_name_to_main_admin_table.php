<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('main_admin', function (Blueprint $table) {
            $table->string('name', 255)->default('Main Administrator')->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('main_admin', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
