<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\Controller;
use App\Models\Treasurer;
use App\Support\LoginSecurity;
use App\Support\PostLogout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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

        $treasurer = Treasurer::where('email', $credentials['login'])
            ->orWhere('treasurer_id', $credentials['login'])
            ->first();
        $security = LoginSecurity::for($request, 'treasurer', $treasurer?->treasurer_id ?? $credentials['login']);
        $security->assertNotLocked('login');
        $security->assertCaptcha($request, 'login');

        if (! $treasurer
            || $treasurer->treasurer_type !== $credentials['treasurer_type']
            || ! Hash::check($credentials['password'], $treasurer->password)) {
            $security->fail('login');
        }

        Auth::guard('treasurer')->login($treasurer, $request->boolean('remember'));
        $security->clear();
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
