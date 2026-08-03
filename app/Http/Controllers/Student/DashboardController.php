<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $student = Auth::guard('student')->user();

        $clearanceRequest = DB::table('clearance_request')
            ->where('student_id', $student->student_id)
            ->orderByDesc('requested_at')
            ->first();

        $subjectClearances = DB::table('clearance_status')
            ->leftJoin('subject_codes', 'clearance_status.subject_id', '=', 'subject_codes.subject_id')
            ->leftJoin('instructor_account', 'clearance_status.instructor_id', '=', 'instructor_account.instructor_id')
            ->where('clearance_status.student_id', $student->student_id)
            ->select(
                'clearance_status.status',
                'clearance_status.remarks',
                'clearance_status.updated_at',
                'subject_codes.subject_code',
                'subject_codes.subject_description',
                'instructor_account.firstname as instructor_firstname',
                'instructor_account.lastname as instructor_lastname'
            )
            ->orderBy('subject_codes.subject_code')
            ->get();

        $officeClearances = DB::table('office_clearance_status')
            ->where('student_id', $student->student_id)
            ->orderBy('office_role')
            ->get();

        $notifications = DB::table('notifications')
            ->where('user_id', $student->student_id)
            ->where('recipient_role', 'student')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        $unreadNotifications = DB::table('notifications')
            ->where('user_id', $student->student_id)
            ->where('recipient_role', 'student')
            ->where('is_read', 0)
            ->count();

        $subjectsTotal = $subjectClearances->count();
        $subjectsApproved = $subjectClearances->where('status', 'Approved')->count();

        $officesTotal = $officeClearances->count();
        $officesApproved = $officeClearances->where('status', 'Approved')->count();

        $clearanceItems = $subjectClearances->map(function ($clearance) {
            $instructor = trim(($clearance->instructor_firstname ?? '').' '.($clearance->instructor_lastname ?? ''));

            return (object) [
                'type' => 'Subject',
                'label' => trim(($clearance->subject_code ?? 'Subject').' — '.($clearance->subject_description ?? '')),
                'owner' => $instructor ?: 'Instructor',
                'status' => $clearance->status ?? 'Pending',
                'remarks' => $clearance->remarks,
                'updated_at' => $clearance->updated_at,
            ];
        })->concat($officeClearances->map(function ($clearance) {
            return (object) [
                'type' => 'Office',
                'label' => ucwords(str_replace(['_', '-'], ' ', $clearance->office_role ?? 'Office clearance')),
                'owner' => 'Office review',
                'status' => $clearance->status ?? 'Pending',
                'remarks' => $clearance->remarks ?? null,
                'updated_at' => $clearance->updated_at ?? null,
            ];
        }));

        $statusGroup = static function ($status): string {
            $status = strtolower(trim((string) $status));

            if (in_array($status, ['approved', 'cleared', 'complete'], true)) {
                return 'approved';
            }

            if (in_array($status, ['rejected', 'disapproved', 'returned', 'needs revision', 'for revision'], true)) {
                return 'action';
            }

            return 'pending';
        };

        $statusBreakdown = [
            'approved' => $clearanceItems->filter(fn ($item) => $statusGroup($item->status) === 'approved')->count(),
            'pending' => $clearanceItems->filter(fn ($item) => $statusGroup($item->status) === 'pending')->count(),
            'action' => $clearanceItems->filter(fn ($item) => $statusGroup($item->status) === 'action')->count(),
        ];
        $totalClearances = $clearanceItems->count();
        $overallProgress = $totalClearances ? (int) round(($statusBreakdown['approved'] / $totalClearances) * 100) : 0;
        $actionItems = $clearanceItems
            ->filter(fn ($item) => $statusGroup($item->status) !== 'approved')
            ->sortByDesc('updated_at')
            ->values();
        $recentActivity = $clearanceItems
            ->filter(fn ($item) => ! empty($item->updated_at))
            ->sortByDesc('updated_at')
            ->take(5)
            ->values();

        return view('student.dashboard', compact(
            'student',
            'clearanceRequest',
            'subjectClearances',
            'officeClearances',
            'notifications',
            'unreadNotifications',
            'subjectsTotal',
            'subjectsApproved',
            'officesTotal',
            'officesApproved',
            'clearanceItems',
            'statusBreakdown',
            'totalClearances',
            'overallProgress',
            'actionItems',
            'recentActivity'
        ));
    }
}
