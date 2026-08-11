<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $registrar = Auth::guard('registrar')->user();

        $registrarRequests = DB::table('office_clearance_status')
            ->join('student_account', 'office_clearance_status.student_id', '=', 'student_account.student_id')
            ->whereRaw("LOWER(TRIM(office_clearance_status.office_role)) = 'registrar'");

        $totalStudents = (clone $registrarRequests)
            ->distinct()
            ->count('student_account.student_id');
        $pendingRequests = (clone $registrarRequests)
            ->where('office_clearance_status.status', '<>', 'Approved')
            ->count();
        $clearedRequests = (clone $registrarRequests)
            ->where('office_clearance_status.status', 'Approved')
            ->count();

        $studentsByProgram = (clone $registrarRequests)
            ->select('student_account.program', DB::raw('count(DISTINCT student_account.student_id) as total'))
            ->groupBy('student_account.program')
            ->orderByDesc('total')
            ->get();

        return view('registrar.dashboard', compact(
            'registrar',
            'totalStudents',
            'pendingRequests',
            'clearedRequests',
            'studentsByProgram'
        ));
    }
}
