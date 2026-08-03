<?php

namespace App\Http\Controllers;

use App\Models\MainAdmin;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationsApiController extends Controller
{
    public function index(Request $request)
    {
        $authenticated = $this->getAuthenticatedUser($request->query('guard'));
        if (! $authenticated) {
            abort(403);
        }

        $notifications = $this->recipientQuery($authenticated)
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'message' => $notification->message,
                'is_read' => (int) $notification->is_read,
                'created_at' => $notification->created_at?->format('M d, Y g:i A'),
                'link_url' => $notification->link_url,
            ]);

        $unread = $this->recipientQuery($authenticated)
            ->where('is_read', 0)
            ->count();

        return response()->json(['notifications' => $notifications, 'unread' => $unread]);
    }

    public function markAllRead(Request $request)
    {
        $authenticated = $this->getAuthenticatedUser($request->query('guard'));
        if (! $authenticated) {
            abort(403);
        }

        $this->recipientQuery($authenticated)
            ->where('is_read', 0)
            ->update(['is_read' => 1]);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, int $notification)
    {
        $authenticated = $this->getAuthenticatedUser($request->query('guard'));
        if (! $authenticated) {
            abort(403);
        }

        $deleted = $this->recipientQuery($authenticated)
            ->whereKey($notification)
            ->delete();

        abort_if($deleted === 0, 404);

        return response()->json(['success' => true]);
    }

    private function getAuthenticatedUser(?string $preferredGuard = null)
    {
        $guards = ['office', 'treasurer', 'registrar', 'student', 'instructor', 'admin'];

        if (in_array($preferredGuard, $guards, true) && Auth::guard($preferredGuard)->check()) {
            return ['user' => Auth::guard($preferredGuard)->user(), 'guard' => $preferredGuard];
        }

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return ['user' => Auth::guard($guard)->user(), 'guard' => $guard];
            }
        }

        if (Auth::check()) {
            return ['user' => Auth::user(), 'guard' => 'web'];
        }

        if (session()->has('admin_id')) {
            $admin = MainAdmin::find(session('admin_id'));
            if ($admin) {
                return ['user' => $admin, 'guard' => 'admin'];
            }
        }

        return null;
    }

    private function recipientQuery(array $authenticated)
    {
        return Notification::query()
            ->where('recipient_role', $authenticated['guard'])
            ->where('user_id', $this->recipientIdentifier(
                $authenticated['guard'],
                $authenticated['user']
            ));
    }

    private function recipientIdentifier(string $guard, object $user): string|int
    {
        $identifierField = match ($guard) {
            'student' => 'student_id',
            'instructor' => 'instructor_id',
            'office' => 'personnel_id',
            'registrar' => 'registrar_id',
            'treasurer' => 'treasurer_id',
            'admin' => 'admin_id',
            default => null,
        };

        return $identifierField && filled($user->{$identifierField} ?? null)
            ? $user->{$identifierField}
            : $user->getAuthIdentifier();
    }
}
