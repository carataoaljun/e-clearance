<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MainAdmin;
use App\Support\PostLogout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    public function showLogin()
    {
        return view('mainAdmin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:100'],
            'password' => ['required', 'string', 'max:128'],
        ]);

        if (Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            /** @var MainAdmin $admin */
            $admin = Auth::guard('admin')->user();
            $request->session()->forget('portal_password_recovery_main_admin');
            $request->session()->regenerate();
            $request->session()->put([
                'admin_id' => $admin->id,
                'admin_name' => $admin->name,
                'admin_email' => $admin->email,
            ]);

            return redirect()->route('dashboard')
                ->with('login_success', 'Login successful. Welcome to the Main Admin panel.');
        }

        return back()->withErrors(['email' => 'Invalid email or password.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return PostLogout::response($request, 'login');
    }
}
