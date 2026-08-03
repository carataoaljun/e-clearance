<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClearanceStatus extends Model
{
    protected $table = 'clearance_status';

    const CREATED_AT = null;

    const UPDATED_AT = 'updated_at';

    protected $fillable = ['student_id', 'subject_id', 'instructor_id', 'status', 'remarks'];

    /**
     * Mirrors the ON DUPLICATE KEY UPDATE upsert used throughout the legacy PHP
     * (unique key: student_id + subject_id + instructor_id).
     */
    public static function upsertStatus(string $studentId, int $subjectId, string $instructorId, array $values): void
    {
        static::updateOrCreate(
            ['student_id' => $studentId, 'subject_id' => $subjectId, 'instructor_id' => $instructorId],
            $values + ['updated_at' => now()]
        );
    }
}
