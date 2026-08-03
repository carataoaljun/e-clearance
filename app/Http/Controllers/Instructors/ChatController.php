<?php

namespace App\Http\Controllers\Instructors;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    // GET /instructor/chat  (was instructor_chat.php GET render)
    public function index()
    {
        $instructor = Auth::guard('instructor')->user();

        $instructorId = $instructor->instructor_id;

        $students = DB::table('instructor_assignment as ia')
            ->join('student_account as sa', function ($join) {
                $join->on('sa.program', '=', 'ia.program')->on('sa.year_level', '=', 'ia.year_level')
                    ->whereRaw('LOWER(TRIM(sa.section)) = LOWER(TRIM(ia.section))');
            })
            ->where('ia.instructor_id', $instructorId)
            ->selectRaw("sa.student_id,
                CONCAT(sa.firstname,' ',sa.lastname) as student_name,
                sa.program, sa.year_level, sa.section,
                (SELECT MAX(created_at) FROM chat_messages
                    WHERE (sender_id = sa.student_id AND receiver_id = ?)
                       OR (sender_id = ? AND receiver_id = sa.student_id)) as last_msg_time,
                (SELECT message FROM chat_messages
                    WHERE (sender_id = sa.student_id AND receiver_id = ?)
                       OR (sender_id = ? AND receiver_id = sa.student_id)
                    ORDER BY created_at DESC LIMIT 1) as last_msg",
                [$instructorId, $instructorId, $instructorId, $instructorId])
            ->distinct()
            ->orderByDesc('last_msg_time')->get();

        $unreadCounts = ChatMessage::where('receiver_id', $instructorId)
            ->where('receiver_role', 'instructor')->where('is_read', 0)->where('sender_role', 'student')
            ->selectRaw('sender_id, COUNT(*) as cnt')->groupBy('sender_id')
            ->pluck('cnt', 'sender_id');

        return view('instructor.instructor.chat', [
            'students' => $students,
            'unreadCounts' => $unreadCounts,
            'totalUnread' => $unreadCounts->sum(),
        ]);
    }

    // POST /instructor/chat/messages  (ajax_send)
    public function send(Request $request)
    {
        $data = $request->validate([
            'receiver_id' => 'required|string',
            'message' => 'required|string|max:2000',
        ]);

        $instructor = Auth::guard('instructor')->user();

        abort_unless($this->isAssignedStudent($instructor->instructor_id, $data['receiver_id']), 403);

        $msg = ChatMessage::create([
            'sender_id' => $instructor->instructor_id, 'sender_role' => 'instructor',
            'receiver_id' => $data['receiver_id'], 'receiver_role' => 'student',
            'message' => $data['message'],
        ]);

        Notification::create([
            'user_id' => $data['receiver_id'],
            'recipient_role' => 'student',
            'message' => "💬 Reply from {$instructor->full_name}: \"".Str::limit($data['message'], 80).'"',
            'notif_type' => 'system',
        ]);

        return response()->json(['success' => true, 'id' => $msg->id]);
    }

    // GET /instructor/chat/messages  (ajax_messages)
    public function messages(Request $request)
    {
        $instructorId = Auth::guard('instructor')->user()->instructor_id;
        $with = $request->query('with', '');
        $since = (int) $request->query('since', 0);

        if (! $with) {
            return response()->json([]);
        }

        abort_unless($this->isAssignedStudent($instructorId, $with), 403);

        ChatMessage::where('sender_id', $with)->where('sender_role', 'student')
            ->where('receiver_id', $instructorId)->where('receiver_role', 'instructor')
            ->where('is_read', 0)->update(['is_read' => 1]);

        $rows = ChatMessage::where(function ($conversation) use ($instructorId, $with) {
            $conversation->where(function ($q) use ($instructorId, $with) {
                $q->where('sender_id', $instructorId)->where('sender_role', 'instructor')
                    ->where('receiver_id', $with)->where('receiver_role', 'student');
            })->orWhere(function ($q) use ($instructorId, $with) {
                $q->where('sender_id', $with)->where('sender_role', 'student')
                    ->where('receiver_id', $instructorId)->where('receiver_role', 'instructor');
            });
        })
            ->where('id', '>', $since)
            ->orderBy('created_at')->limit(100)->get()
            ->map(fn ($m) => [
                'id' => $m->id, 'sender_id' => $m->sender_id, 'sender_role' => $m->sender_role,
                'message' => $m->message, 'is_read' => $m->is_read,
                'time_fmt' => $m->created_at->format('M d Y h:i A'),
                'ts' => $m->created_at->timestamp,
            ]);

        return response()->json($rows);
    }

    private function isAssignedStudent(string $instructorId, string $studentId): bool
    {
        return DB::table('instructor_assignment as ia')
            ->join('student_account as sa', function ($join) {
                $join->on('sa.program', '=', 'ia.program')->on('sa.year_level', '=', 'ia.year_level')
                    ->whereRaw('LOWER(TRIM(sa.section)) = LOWER(TRIM(ia.section))');
            })
            ->where('ia.instructor_id', $instructorId)
            ->where('sa.student_id', $studentId)
            ->exists();
    }
}
