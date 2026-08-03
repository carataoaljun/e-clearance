<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 100)->index();
            $table->string('actor_guard', 30)->nullable();
            $table->string('actor_id', 100)->nullable();
            $table->string('subject_type', 100)->nullable();
            $table->string('subject_id', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['actor_guard', 'actor_id'], 'security_audit_actor');
            $table->index(['subject_type', 'subject_id'], 'security_audit_subject');
        });

        Schema::create('clearance_verification_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('student_id', 50)->unique();
            $table->char('token_hash', 64)->unique();
            $table->text('token_encrypted');
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamp('last_verified_at')->nullable();

            $table->foreign('student_id')
                ->references('student_id')
                ->on('student_account')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clearance_verification_tokens');
        Schema::dropIfExists('security_audit_logs');
    }
};
