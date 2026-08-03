<?php

namespace App\Support;

use App\Models\StudentAccount;
use Illuminate\Support\Facades\DB;

final class ClearanceWorkflow
{
    public const OFFICE_ROLES = [
        'section treasurer',
        'department treasurer',
        'property custodian',
        'scc adviser',
        'sas director',
        'guidance office',
        'library',
        'dean',
        'registrar',
    ];

    public static function normalizeOfficeRole(string $officeRole): ?string
    {
        $normalized = strtolower(trim(str_replace(['_', '-'], ' ', $officeRole)));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?: '';

        $role = match (true) {
            (str_contains($normalized, 'section') && str_contains($normalized, 'treasurer')),
            $normalized === 'section' => 'section treasurer',
            (str_contains($normalized, 'department') && str_contains($normalized, 'treasurer')),
            $normalized === 'department' => 'department treasurer',
            str_contains($normalized, 'dean'), str_contains($normalized, 'program head'),
            str_contains($normalized, 'department head') => 'dean',
            str_contains($normalized, 'registrar') => 'registrar',
            in_array($normalized, ['ssc adviser', 'ssc advisor', 'scc advisor'], true) => 'scc adviser',
            default => $normalized,
        };

        return in_array($role, self::OFFICE_ROLES, true) ? $role : null;
    }

    public static function instructorIsAssigned(
        StudentAccount $student,
        int $subjectId,
        string $instructorId,
    ): bool {
        return DB::table('instructor_assignment')
            ->where('subject_id', $subjectId)
            ->where('instructor_id', $instructorId)
            ->where('program', $student->program)
            ->where('year_level', $student->year_level)
            ->whereRaw('LOWER(TRIM(section)) = LOWER(TRIM(?))', [$student->section])
            ->exists();
    }

    public static function prerequisitesMet(StudentAccount $student, string $officeRole): bool
    {
        $officeRole = self::normalizeOfficeRole($officeRole) ?? '';

        $requiredOffices = match ($officeRole) {
            'department treasurer' => ['section treasurer'],
            'dean' => ['section treasurer', 'department treasurer'],
            'registrar' => [
                'section treasurer', 'department treasurer', 'property custodian', 'scc adviser',
                'sas director', 'guidance office', 'library', 'dean',
            ],
            default => [],
        };

        if ($requiredOffices !== []) {
            $approved = DB::table('office_clearance_status')
                ->where('student_id', $student->student_id)
                ->where('status', 'Approved')
                ->pluck('office_role')
                ->map(fn ($role) => self::normalizeOfficeRole((string) $role))
                ->filter()
                ->unique()
                ->intersect($requiredOffices)
                ->count();

            if ($approved !== count($requiredOffices)) {
                return false;
            }
        }

        return ! in_array($officeRole, ['dean', 'registrar'], true)
            || self::allInstructorClearancesApproved($student);
    }

    public static function canOpenOfficeRequest(StudentAccount $student, string $officeRole): bool
    {
        $officeRole = self::normalizeOfficeRole($officeRole);
        if ($officeRole === null) {
            return false;
        }

        $status = DB::table('office_clearance_status')
            ->where('student_id', $student->student_id)
            ->get(['office_role', 'status'])
            ->first(fn ($record) => self::normalizeOfficeRole((string) $record->office_role) === $officeRole)
            ?->status;

        return $status === null || $status === 'Rejected';
    }

    public static function allInstructorClearancesApproved(StudentAccount $student): bool
    {
        $assignments = DB::table('instructor_assignment')
            ->where('program', $student->program)
            ->where('year_level', $student->year_level)
            ->whereRaw('LOWER(TRIM(section)) = LOWER(TRIM(?))', [$student->section])
            ->get(['subject_id', 'instructor_id']);

        if ($assignments->isEmpty()) {
            return false;
        }

        return $assignments->every(fn ($assignment) => DB::table('clearance_status')
            ->where('student_id', $student->student_id)
            ->where('subject_id', $assignment->subject_id)
            ->where('instructor_id', $assignment->instructor_id)
            ->where('status', 'Approved')
            ->exists());
    }
}
