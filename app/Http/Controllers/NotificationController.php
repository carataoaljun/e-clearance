<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = DB::table('notifications')->orderByDesc('created_at')->get();

        return view('mainAdmin.notifications.index', compact('notifications'));
    }
}
