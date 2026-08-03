<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function index()
    {
        $messages = DB::table('chat_support')->orderByDesc('created_at')->paginate(25);

        return view('mainAdmin.chat.index', compact('messages'));
    }
}
