<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_personnel', function (Blueprint $table) {
            $table->id();
            $table->string('personnel_id', 50)->nullable()->unique();
            $table->string('firstname', 100);
            $table->string('lastname', 100);
            $table->string('email', 100)->unique();
            $table->string('password', 255);
            $table->string('office', 100)->nullable();
            $table->string('role', 50)->default('admin_personnel');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_personnel');
    }
};
