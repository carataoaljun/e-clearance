<?php

namespace App\Http\Controllers\Instructors;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // GET /instructor/login  (was index.php)
    public function showLogin()
    {
        if (Auth::guard('instructor')->check()) {
            return redirect()->route('instructor.dashboard');
        }

        return view('instructor.instructor.login');
    }

    // POST /instructor/login
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:100'],
            'password' => ['required', 'string', 'max:128'],
        ]);

        $remember = $request->boolean('remember');
        if (Auth::guard('instructor')->attempt($data, $remember)) {
            $request->session()->forget('portal_password_recovery_instructor');
            $request->session()->regenerate();

            return redirect()->route('instructor.dashboard');
        }

        return back()
            ->withErrors(['email' => 'Invalid email or password.'])
            ->onlyInput('email');
    }

    // POST /instructor/logout (was logout.php)
    public function logout(Request $request)
    {
        Auth::guard('instructor')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('instructor.login');
    }
}
