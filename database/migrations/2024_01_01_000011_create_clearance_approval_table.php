<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clearance_approval', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clearance_id');
            $table->string('approver_role', 50)->nullable();
            $table->string('approver_id', 50)->nullable();
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->text('remarks')->nullable();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['clearance_id', 'approver_role'], 'unique_clearance_role');

            $table->foreign('clearance_id')
                ->references('id')
                ->on('clearance_request')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clearance_approval');
    }
};
