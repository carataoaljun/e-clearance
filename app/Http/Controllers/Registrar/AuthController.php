<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\Registrar;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

        if (! $registrar || ! Hash::check($credentials['password'], $registrar->password)) {
            AuditLogger::record('authentication.failed', 'registrar', null, null, null, [
                'guard' => 'registrar',
                'identifier_hash' => hash('sha256', Str::lower($credentials['login'])),
            ]);
            throw ValidationException::withMessages([
                'login' => 'The credentials you entered are incorrect.',
            ]);
        }

        Auth::guard('registrar')->login($registrar, $request->boolean('remember'));
        $request->session()->forget('portal_password_recovery_registrar');
        $request->session()->regenerate();

        return redirect()->route('registrar.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('registrar')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('registrar.login')->with('status', 'You have been logged out.');
    }
}
