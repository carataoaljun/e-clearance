<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_support', function (Blueprint $table) {
            $table->id();
            $table->string('sender_id', 50);
            $table->string('sender_role', 50);
            $table->text('message');
            $table->string('status', 20)->default('unread');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_support');
    }
};
