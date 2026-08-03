<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Signature snapshots attached to clearance records
        Schema::create('clearance_signatures', function (Blueprint $table) {
            $table->id();
            // FK → clearance_request.id  (0 = instructor-level only)
            $table->unsignedBigInteger('clearance_id');
            $table->string('student_id', 50);
            $table->string('signer_id', 50);
            $table->string('signer_role', 50);
            $table->string('signer_name', 200);
            // Snapshot of the Base-64 PNG at signing time
            $table->longText('signature_data');
            $table->timestamp('signed_at')->useCurrent();

            $table->index('clearance_id', 'idx_clearance');
            $table->index('student_id', 'clearance_signatures_student_idx');

            $table->foreign('clearance_id')
                ->references('id')
                ->on('clearance_request')
                ->onDelete('cascade');

            $table->foreign('student_id')
                ->references('student_id')
                ->on('student_account')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clearance_signatures');
    }
};
