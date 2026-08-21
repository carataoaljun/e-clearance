<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

class PortalPasswordCodeController extends Controller
{
    public function sendCode(Request $request, string $portal)
    {
        $config = $this->portalConfig($portal);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
        ], [
            'email.required' => "Enter the email registered to your {$config['name']} account.",
            'email.email' => 'Enter a valid email address.',
        ]);
        $email = strtolower(trim($data['email']));
        $account = DB::table($config['table'])->whereRaw('LOWER(email) = ?', [$email])->first();

        AuditLogger::record('password_reset.requested', $config['guard'], null, $config['table'], null, [
            'identifier_hash' => hash('sha256', $email),
        ]);

        if (! $account) {
            return redirect()->route($config['loginRoute'])
                ->with('recovery_status', "If that email belongs to a {$config['name']} account, a verification code has been sent.");
        }

        $code = (string) random_int(100000, 999999);
        $accountName = trim(($account->firstname ?? '').' '.($account->lastname ?? '')) ?: $config['name'];

        try {
            Mail::send('emails.portal-password-code', [
                'accountName' => $accountName,
                'portalName' => $config['name'],
                'code' => $code,
                'expiresInMinutes' => 10,
            ], function ($message) use ($email, $config) {
                $message->to($email)->subject("Your ClearanceMS {$config['name']} verification code");
            });
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withInput(['recovery_action' => 'email', 'recovery_portal' => $portal, 'email' => $email])
                ->withErrors(['email' => 'The verification email could not be sent. Please try again shortly.']);
        }

        $request->session()->put($this->sessionKey($portal), [
            'email' => $email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10)->timestamp,
            'attempts' => 0,
            'stage' => 'code',
        ]);

        return redirect()->route($config['loginRoute'])
            ->with('recovery_status', 'A six-digit verification code was sent to your registered email.');
    }

    public function verifyCode(Request $request, string $portal)
    {
        $config = $this->portalConfig($portal);
        $data = $request->validate([
            'verification_code' => ['required', 'digits:6'],
        ], [
            'verification_code.required' => 'Enter the six-digit verification code.',
            'verification_code.digits' => 'The verification code must contain exactly six digits.',
        ]);
        $key = $this->sessionKey($portal);
        $recovery = $request->session()->get($key);

        if (! is_array($recovery) || ($recovery['stage'] ?? null) !== 'code') {
            return redirect()->route($config['loginRoute'])->withErrors(['email' => 'Start a new password recovery request.'])
                ->withInput(['recovery_action' => 'email', 'recovery_portal' => $portal]);
        }

        if (now()->timestamp > ($recovery['expires_at'] ?? 0)) {
            $email = $recovery['email'] ?? '';
            $request->session()->forget($key);

            return redirect()->route($config['loginRoute'])->withErrors(['email' => 'The verification code expired. Request a new code.'])
                ->withInput(['recovery_action' => 'email', 'recovery_portal' => $portal, 'email' => $email]);
        }

        $recovery['attempts'] = (int) ($recovery['attempts'] ?? 0) + 1;

        if (! Hash::check($data['verification_code'], $recovery['code_hash'] ?? '')) {
            if ($recovery['attempts'] >= 5) {
                $email = $recovery['email'] ?? '';
                $request->session()->forget($key);

                return redirect()->route($config['loginRoute'])->withErrors(['email' => 'Too many incorrect attempts. Request a new code.'])
                    ->withInput(['recovery_action' => 'email', 'recovery_portal' => $portal, 'email' => $email]);
            }

            $request->session()->put($key, $recovery);

            return back()->withErrors(['verification_code' => 'The verification code is incorrect. Please try again.']);
        }

        unset($recovery['code_hash'], $recovery['expires_at'], $recovery['attempts']);
        $recovery['stage'] = 'reset';
        $recovery['verified_at'] = now()->timestamp;
        $request->session()->put($key, $recovery);

        return redirect()->route($config['loginRoute'])
            ->with('recovery_status', 'Email verified. You can now create your new password.');
    }

    public function resetPassword(Request $request, string $portal)
    {
        $config = $this->portalConfig($portal);
        $data = $request->validate([
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);
        $key = $this->sessionKey($portal);
        $recovery = $request->session()->get($key);
        $verifiedAt = (int) ($recovery['verified_at'] ?? 0);

        if (! is_array($recovery) || ($recovery['stage'] ?? null) !== 'reset'
            || $verifiedAt === 0 || now()->timestamp - $verifiedAt > 900) {
            $request->session()->forget($key);

            return redirect()->route($config['loginRoute'])->withErrors(['email' => 'Your verified reset session expired. Request a new code.'])
                ->withInput(['recovery_action' => 'email', 'recovery_portal' => $portal]);
        }

        $updated = DB::table($config['table'])->whereRaw('LOWER(email) = ?', [$recovery['email']])
            ->update(['password' => Hash::make($data['password'])]);

        if (! $updated) {
            $request->session()->forget($key);

            return redirect()->route($config['loginRoute'])->withErrors(['email' => 'The account is no longer available.']);
        }

        $request->session()->forget($key);
        $request->session()->regenerateToken();

        AuditLogger::record('password_reset.completed', $config['guard'], null, $config['table'], null, [
            'identifier_hash' => hash('sha256', strtolower((string) $recovery['email'])),
        ]);

        return redirect()->route($config['loginRoute'])
            ->with('status', 'Your password was changed successfully. You can now sign in.');
    }

    public function cancel(Request $request, string $portal)
    {
        $config = $this->portalConfig($portal);
        $request->session()->forget($this->sessionKey($portal));

        return redirect()->route($config['loginRoute']);
    }

    private function sessionKey(string $portal): string
    {
        return 'portal_password_recovery_'.str_replace('-', '_', $portal);
    }

    private function portalConfig(string $portal): array
    {
        $portals = [
            'main-admin' => ['name' => 'Main Administrator', 'loginRoute' => 'login', 'table' => 'main_admin', 'guard' => 'admin'],
            'instructor' => ['name' => 'Instructor', 'loginRoute' => 'instructor.login', 'table' => 'instructor_account', 'guard' => 'instructor'],
            'office' => ['name' => 'Office Personnel', 'loginRoute' => 'office.login', 'table' => 'admin_personnel', 'guard' => 'office'],
            'registrar' => ['name' => 'Registrar', 'loginRoute' => 'registrar.login', 'table' => 'registrar', 'guard' => 'registrar'],
            'treasurer' => ['name' => 'Treasurer', 'loginRoute' => 'treasurer.login', 'table' => 'treasurers', 'guard' => 'treasurer'],
        ];

        abort_unless(isset($portals[$portal]), 404);

        return $portals[$portal];
    }
}
