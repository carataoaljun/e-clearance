<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatSupportController extends Controller
{
    public function index()
    {
        $student = Auth::guard('student')->user();

        $instructors = DB::table('instructor_assignment as ia')
            ->join('instructor_account as iac', 'iac.instructor_id', '=', 'ia.instructor_id')
            ->where('ia.program', $student->program)
            ->where('ia.year_level', $student->year_level)
            ->whereRaw('LOWER(TRIM(ia.section)) = LOWER(TRIM(?))', [$student->section])
            ->select('iac.instructor_id', DB::raw("CONCAT(iac.firstname, ' ', iac.lastname) as instructor_name"), 'iac.department')
            ->distinct()
            ->orderBy('instructor_name')
            ->get();

        $unreadCounts = ChatMessage::where('receiver_id', $student->student_id)
            ->where('receiver_role', 'student')->where('sender_role', 'instructor')->where('is_read', 0)
            ->selectRaw('sender_id, COUNT(*) as count')->groupBy('sender_id')->pluck('count', 'sender_id');

        return view('student.chat-support', compact('student', 'instructors', 'unreadCounts'));
    }

    public function send(Request $request)
    {
        $student = Auth::guard('student')->user();
        $data = $request->validate(['receiver_id' => 'required|string', 'message' => 'required|string|max:2000']);
        abort_unless($this->isAssignedInstructor($student, $data['receiver_id']), 403);

        ChatMessage::create([
            'sender_id' => $student->student_id, 'sender_role' => 'student',
            'receiver_id' => $data['receiver_id'], 'receiver_role' => 'instructor',
            'message' => $data['message'],
        ]);

        Notification::create([
            'user_id' => $data['receiver_id'],
            'recipient_role' => 'instructor',
            'message' => "💬 New message from {$student->full_name}",
            'notif_type' => 'message',
            'link_url' => route('instructor.chat'),
        ]);

        return response()->json(['success' => true]);
    }

    public function messages(Request $request)
    {
        $student = Auth::guard('student')->user();
        $with = $request->string('with')->toString();
        abort_unless($with && $this->isAssignedInstructor($student, $with), 403);
        $since = (int) $request->query('since', 0);

        ChatMessage::where('sender_id', $with)->where('sender_role', 'instructor')
            ->where('receiver_id', $student->student_id)->where('receiver_role', 'student')
            ->where('is_read', 0)->update(['is_read' => 1]);

        return response()->json(ChatMessage::where(function ($conversation) use ($student, $with) {
            $conversation->where(function ($query) use ($student, $with) {
                $query->where('sender_id', $student->student_id)->where('sender_role', 'student')
                    ->where('receiver_id', $with)->where('receiver_role', 'instructor');
            })->orWhere(function ($query) use ($student, $with) {
                $query->where('sender_id', $with)->where('sender_role', 'instructor')
                    ->where('receiver_id', $student->student_id)->where('receiver_role', 'student');
            });
        })->where('id', '>', $since)->orderBy('id')->limit(100)->get()->map(fn ($message) => [
            'id' => $message->id, 'sender_role' => $message->sender_role, 'message' => $message->message,
            'time_fmt' => $message->created_at->format('M d, g:i A'), 'is_read' => $message->is_read,
        ]));
    }

    private function isAssignedInstructor(object $student, string $instructorId): bool
    {
        return DB::table('instructor_assignment')->where('instructor_id', $instructorId)
            ->where('program', $student->program)->where('year_level', $student->year_level)
            ->whereRaw('LOWER(TRIM(section)) = LOWER(TRIM(?))', [$student->section])->exists();
    }
}
