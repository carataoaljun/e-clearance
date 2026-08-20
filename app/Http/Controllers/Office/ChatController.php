<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\StaffChatController;

class ChatController extends StaffChatController
{
    protected function guard(): string
    {
        return 'office';
    }

    protected function view(): string
    {
        return 'office.chat';
    }

    protected function subheading(): string
    {
        return 'Message students about the requirements this office reviews.';
    }
}
