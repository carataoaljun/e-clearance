<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Models\AdminPersonnel;
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
        if (Auth::guard('office')->check()) {
            return redirect()->route('office.dashboard');
        }

        return view('office.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:100'], // personnel_id or email
            'password' => ['required', 'string', 'max:128'],
            'role' => ['required', 'string', 'in:'.implode(',', array_keys(AdminPersonnel::$validRoles))],
        ]);

        $personnel = AdminPersonnel::where('email', $credentials['login'])
            ->orWhere('personnel_id', $credentials['login'])
            ->first();

        if (! $personnel || ! Hash::check($credentials['password'], $personnel->password)) {
            $this->auditFailure($credentials['login']);
            throw ValidationException::withMessages([
                'login' => 'The credentials you entered are incorrect.',
            ]);
        }

        if ($personnel->role !== $credentials['role']) {
            $this->auditFailure($credentials['login']);
            throw ValidationException::withMessages([
                'role' => 'The selected role does not match this account.',
            ]);
        }

        Auth::guard('office')->login($personnel, $request->boolean('remember'));
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

    private function auditFailure(string $identifier): void
    {
        AuditLogger::record('authentication.failed', 'office', null, null, null, [
            'guard' => 'office',
            'identifier_hash' => hash('sha256', Str::lower($identifier)),
        ]);
    }
}
