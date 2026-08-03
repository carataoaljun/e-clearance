<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_password_resets', function (Blueprint $table) {
            $table->id();
            $table->string('portal', 30);
            $table->string('email', 150);
            $table->string('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['portal', 'email']);
            $table->index(['portal', 'token_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_password_resets');
    }
};
