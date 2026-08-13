<?php

namespace App\Http\Controllers\Instructors;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Support\LoginSecurity;
use App\Support\PostLogout;
use App\Support\StrongPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
            'password' => StrongPassword::loginRules(),
        ], StrongPassword::loginMessages());

        $security = LoginSecurity::for($request, 'instructor', $data['email']);
        $security->assertNotLocked('email');
        $security->assertCaptcha($request, 'email');

        $instructor = Instructor::whereRaw('LOWER(email) = ?', [strtolower(trim($data['email']))])->first();
        if (! $instructor || ! Hash::check($data['password'], $instructor->password)) {
            $security->fail('email');
        }

        Auth::guard('instructor')->login($instructor, $request->boolean('remember'));
        $security->clear();
        $request->session()->forget('portal_password_recovery_instructor');
        $request->session()->regenerate();

        return redirect()->route('instructor.dashboard')
            ->with('login_success', 'Login successful. Welcome to the Instructor panel.');
    }

    // POST /instructor/logout (was logout.php)
    public function logout(Request $request)
    {
        Auth::guard('instructor')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return PostLogout::response($request, 'instructor.login');
    }
}
