<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clearance_request', function (Blueprint $table) {
            $table->id();
            $table->string('student_id', 50);
            $table->enum('status', ['Pending', 'Approved', 'Rejected', 'Cleared'])->default('Pending');
            $table->text('remarks')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('student_id')
                ->references('student_id')
                ->on('student_account')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clearance_request');
    }
};
