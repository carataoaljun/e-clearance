<?php

namespace App\Http\Controllers\Instructors;

use App\Http\Controllers\StaffChatController;

class ChatController extends StaffChatController
{
    protected function guard(): string
    {
        return 'instructor';
    }

    protected function view(): string
    {
        return 'instructor.instructor.chat';
    }

    protected function subheading(): string
    {
        return 'Search and message students assigned to your classes.';
    }
}
