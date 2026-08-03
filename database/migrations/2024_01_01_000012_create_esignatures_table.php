<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esignatures', function (Blueprint $table) {
            $table->id();
            // instructor_id / admin personnel_id / registrar_id
            $table->string('signer_id', 50);
            // instructor | admin_personnel | registrar | program_head_bsit …
            $table->string('signer_role', 50);
            $table->string('signer_name', 200);
            // Base-64 PNG data-URI drawn on canvas
            $table->longText('signature_data');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['signer_id', 'signer_role'], 'unique_signer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esignatures');
    }
};
