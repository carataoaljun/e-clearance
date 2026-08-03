<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->string('sender_id', 50);
            $table->string('sender_role', 20)->default('student');
            $table->string('receiver_id', 50);
            $table->string('receiver_role', 20)->default('instructor');
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sender_id', 'receiver_id'], 'idx_convo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
