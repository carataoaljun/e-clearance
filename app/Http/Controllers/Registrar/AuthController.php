<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\Registrar;
use App\Support\LoginSecurity;
use App\Support\PostLogout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('registrar')->check()) {
            return redirect()->route('registrar.dashboard');
        }

        return view('registrar.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:100'], // email or registrar_id
            'password' => ['required', 'string', 'max:128'],
        ]);

        $registrar = Registrar::where('email', $credentials['login'])
            ->orWhere('registrar_id', $credentials['login'])
            ->first();
        $security = LoginSecurity::for($request, 'registrar', $registrar?->registrar_id ?? $credentials['login']);
        $security->assertNotLocked('login');
        $security->assertCaptcha($request, 'login');

        if (! $registrar || ! Hash::check($credentials['password'], $registrar->password)) {
            $security->fail('login');
        }

        Auth::guard('registrar')->login($registrar, $request->boolean('remember'));
        $security->clear();
        $request->session()->forget('portal_password_recovery_registrar');
        $request->session()->regenerate();

        return redirect()->route('registrar.dashboard')
            ->with('login_success', 'Login successful. Welcome to the Registrar panel.');
    }

    public function logout(Request $request)
    {
        Auth::guard('registrar')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return PostLogout::response($request, 'registrar.login');
    }
}
