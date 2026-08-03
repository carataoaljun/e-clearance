<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('recipient_role', 30)->nullable()->after('user_id');
            $table->index(['recipient_role', 'user_id', 'is_read'], 'notifications_recipient_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_recipient_lookup');
            $table->dropColumn('recipient_role');
        });
    }
};
