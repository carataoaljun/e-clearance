<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_account', function (Blueprint $table) {
            $table->id();
            $table->string('instructor_id', 50)->unique();
            $table->string('firstname', 100);
            $table->string('middlename', 100)->nullable();
            $table->string('lastname', 100);
            $table->string('suffix', 10)->nullable();
            $table->string('email', 100)->unique();
            $table->string('password', 255);
            $table->string('department', 100);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_account');
    }
};
