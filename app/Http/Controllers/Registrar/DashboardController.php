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

        $totalStudents = DB::table('student_account')->count();

        $pendingRequests = DB::table('office_clearance_status')->where('status', 'Pending')->count();
        $clearedRequests = DB::table('office_clearance_status')->where('status', 'Approved')->count();

        $studentsByProgram = DB::table('student_account')
            ->select('program', DB::raw('count(*) as total'))
            ->groupBy('program')
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
