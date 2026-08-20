<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\StaffChatController;

class ChatController extends StaffChatController
{
    protected function guard(): string
    {
        return 'registrar';
    }

    protected function view(): string
    {
        return 'registrar.chat';
    }

    protected function subheading(): string
    {
        return 'Message students about final clearance and records.';
    }
}
