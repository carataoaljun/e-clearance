<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\StaffChatController;

class ChatController extends StaffChatController
{
    protected function guard(): string
    {
        return 'treasurer';
    }

    protected function view(): string
    {
        return 'treasurer.chat';
    }

    protected function subheading(): string
    {
        return 'Message students about balances and financial clearance.';
    }
}
