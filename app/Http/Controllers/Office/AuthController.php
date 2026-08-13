<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Models\AdminPersonnel;
use App\Support\LoginSecurity;
use App\Support\PostLogout;
use App\Support\StrongPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('office')->check()) {
            return redirect()->route('office.dashboard');
        }

        return view('office.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:100'], // personnel_id or email
            'password' => StrongPassword::loginRules(),
            'role' => ['required', 'string', 'in:'.implode(',', array_keys(AdminPersonnel::$validRoles))],
        ], StrongPassword::loginMessages());

        $personnel = AdminPersonnel::where('email', $credentials['login'])
            ->orWhere('personnel_id', $credentials['login'])
            ->first();
        $security = LoginSecurity::for($request, 'office', $personnel?->personnel_id ?? $credentials['login']);
        $security->assertNotLocked('login');
        $security->assertCaptcha($request, 'login');

        if (! $personnel || ! Hash::check($credentials['password'], $personnel->password)) {
            $security->fail('login');
        }

        if ($personnel->role !== $credentials['role']) {
            $security->fail('login', 'role');
        }

        Auth::guard('office')->login($personnel, $request->boolean('remember'));
        $security->clear();
        $request->session()->forget('portal_password_recovery_office');
        $request->session()->regenerate();

        return redirect()->route('office.dashboard')
            ->with('login_success', 'Login successful. Welcome to the Office panel.');
    }

    public function logout(Request $request)
    {
        Auth::guard('office')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return PostLogout::response($request, 'office.login');
    }
}
