<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\Controller;
use App\Models\Treasurer;
use App\Support\AuditLogger;
use App\Support\PostLogout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('treasurer')->check()) {
            return redirect()->route('treasurer.dashboard');
        }

        return view('treasurer.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:100'], // treasurer_id or email
            'password' => ['required', 'string', 'max:128'],
            'treasurer_type' => ['required', 'string', 'in:department,section'],
        ]);

        $treasurer = Treasurer::where('treasurer_type', $credentials['treasurer_type'])
            ->where(function ($query) use ($credentials) {
                $query->where('email', $credentials['login'])
                    ->orWhere('treasurer_id', $credentials['login']);
            })
            ->first();

        if (! $treasurer || ! Hash::check($credentials['password'], $treasurer->password)) {
            AuditLogger::record('authentication.failed', 'treasurer', null, null, null, [
                'guard' => 'treasurer',
                'identifier_hash' => hash('sha256', Str::lower($credentials['login'])),
            ]);
            throw ValidationException::withMessages([
                'login' => 'The credentials you entered are incorrect.',
            ]);
        }

        Auth::guard('treasurer')->login($treasurer, $request->boolean('remember'));
        $request->session()->forget('portal_password_recovery_treasurer');
        $request->session()->regenerate();

        return redirect()->route('treasurer.dashboard')
            ->with('login_success', 'Login successful. Welcome to the Treasurer panel.');
    }

    public function logout(Request $request)
    {
        Auth::guard('treasurer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return PostLogout::response($request, 'treasurer.login');
    }
}
