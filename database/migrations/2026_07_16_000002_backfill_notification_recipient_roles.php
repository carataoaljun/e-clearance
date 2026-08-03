<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $recipientSources = [
            'student' => ['student_account', 'student_id'],
            'instructor' => ['instructor_account', 'instructor_id'],
            'office' => ['admin_personnel', 'personnel_id'],
            'registrar' => ['registrar', 'registrar_id'],
            'treasurer' => ['treasurers', 'treasurer_id'],
        ];

        foreach ($recipientSources as $role => [$table, $identifier]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)
                ->whereNotNull($identifier)
                ->orderBy($identifier)
                ->pluck($identifier)
                ->chunk(500)
                ->each(function ($identifiers) use ($role) {
                    DB::table('notifications')
                        ->whereNull('recipient_role')
                        ->whereIn('user_id', $identifiers->all())
                        ->update(['recipient_role' => $role]);
                });
        }
    }

    public function down(): void
    {
        // Recipient roles cannot be safely distinguished from newly created rows.
    }
};
