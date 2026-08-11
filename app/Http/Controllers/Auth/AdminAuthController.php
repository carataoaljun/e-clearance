<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MainAdmin;
use App\Support\AuditLogger;
use App\Support\LoginSecurity;
use App\Support\PostLogout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

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

        $security = LoginSecurity::for($request, 'admin', $credentials['email']);
        $security->assertNotLocked('email');
        $security->assertCaptcha($request, 'email');

        $admin = MainAdmin::whereRaw('LOWER(email) = ?', [strtolower(trim($credentials['email']))])->first();
        if (! $admin || ! Hash::check($credentials['password'], $admin->password)) {
            $security->fail('email');
        }

        $security->clear();
        $request->session()->forget('portal_password_recovery_main_admin');
        $request->session()->regenerate();

        return $this->sendLoginCode($request, $admin);
    }

    public function verifyLoginCode(Request $request)
    {
        $data = $request->validate([
            'verification_code' => ['required', 'digits:6'],
        ]);
        $challenge = $request->session()->get('admin_login_challenge');

        if (! is_array($challenge) || ($challenge['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget('admin_login_challenge');

            throw ValidationException::withMessages([
                'verification_code' => 'This sign-in code has expired. Please enter your password again.',
            ]);
        }

        $attempts = (int) ($challenge['attempts'] ?? 0);
        if (! Hash::check($data['verification_code'], (string) ($challenge['code_hash'] ?? ''))) {
            $attempts++;
            $challenge['attempts'] = $attempts;
            $request->session()->put('admin_login_challenge', $challenge);
            AuditLogger::record('authentication.mfa_failed', 'admin', metadata: [
                'admin_id' => $challenge['admin_id'] ?? null,
                'attempts' => $attempts,
            ]);

            if ($attempts >= (int) config('login_security.admin_otp_max_attempts', 5)) {
                $request->session()->forget('admin_login_challenge');
                AuditLogger::record('authentication.mfa_locked', 'admin', metadata: [
                    'admin_id' => $challenge['admin_id'] ?? null,
                ]);

                throw ValidationException::withMessages([
                    'verification_code' => 'Too many incorrect codes. Please enter your password again.',
                ]);
            }

            throw ValidationException::withMessages([
                'verification_code' => 'The verification code is incorrect.',
            ]);
        }

        $admin = MainAdmin::find($challenge['admin_id'] ?? null);
        if (! $admin) {
            $request->session()->forget('admin_login_challenge');
            throw ValidationException::withMessages([
                'verification_code' => 'This sign-in request is no longer valid.',
            ]);
        }

        // Remove the challenge before authenticating so the code cannot be replayed.
        $request->session()->forget('admin_login_challenge');
        Auth::guard('admin')->login($admin, false);
        $request->session()->regenerate();
        $request->session()->put([
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'admin_email' => $admin->email,
        ]);
        AuditLogger::record('authentication.mfa_verified', 'admin', $admin);

        return redirect()->route('dashboard')
            ->with('login_success', 'Login successful. Welcome to the Main Admin panel.');
    }

    public function resendLoginCode(Request $request)
    {
        $challenge = $request->session()->get('admin_login_challenge');
        $admin = is_array($challenge) ? MainAdmin::find($challenge['admin_id'] ?? null) : null;

        if (! $admin) {
            $request->session()->forget('admin_login_challenge');

            return redirect()->route('login')->withErrors([
                'email' => 'Your sign-in request expired. Please enter your password again.',
            ]);
        }

        return $this->sendLoginCode($request, $admin, true);
    }

    public function cancelLoginCode(Request $request)
    {
        $request->session()->forget('admin_login_challenge');

        return redirect()->route('login');
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return PostLogout::response($request, 'login');
    }

    private function sendLoginCode(Request $request, MainAdmin $admin, bool $resend = false)
    {
        $code = (string) random_int(100000, 999999);
        $lifetime = max(60, (int) config('login_security.admin_otp_lifetime_seconds', 600));

        try {
            Mail::send('emails.main-admin-login-code', [
                'adminName' => $admin->name,
                'code' => $code,
                'expiresInMinutes' => (int) ceil($lifetime / 60),
            ], function ($message) use ($admin) {
                $message->to($admin->email)->subject('Your Main Admin sign-in code');
            });
        } catch (\Throwable $exception) {
            report($exception);
            $request->session()->forget('admin_login_challenge');

            return redirect()->route('login')->withErrors([
                'email' => 'The verification email could not be sent. Please try again shortly.',
            ]);
        }

        $request->session()->put('admin_login_challenge', [
            'admin_id' => $admin->id,
            'email' => $admin->email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addSeconds($lifetime)->timestamp,
            'attempts' => 0,
        ]);
        AuditLogger::record('authentication.mfa_challenge_sent', 'admin', null, 'main_admin', $admin->id, [
            'resend' => $resend,
            'expires_in_seconds' => $lifetime,
        ]);

        $response = redirect()->route('login')->with(
            'status',
            $resend ? 'A new sign-in code was sent to your email.' : 'Password verified. Check your email for the sign-in code.',
        );

        if (app()->environment('local') && config('mail.default') === 'log') {
            $response->with('local_verification_code', $code);
        }

        return $response;
    }
}
