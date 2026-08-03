<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountRecoveryController extends Controller
{
    public function show(string $portal): View
    {
        $config = $this->portalConfig($portal);

        return view('auth.account-recovery', [
            'portal' => $portal,
            'portalName' => $config['name'],
            'loginRoute' => $config['loginRoute'],
        ]);
    }

    public function sendResetLink(Request $request, string $portal): RedirectResponse
    {
        $config = $this->portalConfig($portal);
        $data = $request->validate(['email' => ['required', 'email', 'max:150']]);
        $email = strtolower(trim($data['email']));
        $accountExists = DB::table($config['table'])->whereRaw('LOWER(email) = ?', [$email])->exists();

        AuditLogger::record('password_reset.requested', $config['guard'], null, $config['table'], null, [
            'identifier_hash' => hash('sha256', $email),
        ]);

        $localResetUrl = null;
        if ($accountExists) {
            $token = Str::random(64);
            DB::table('account_password_resets')->updateOrInsert(
                ['portal' => $portal, 'email' => $email],
                ['token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(30), 'created_at' => now()]
            );

            $resetUrl = route('account-recovery.reset', ['portal' => $portal, 'token' => $token]).'?email='.urlencode($email);
            Mail::send('emails.account-password-reset', [
                'portalName' => $config['name'],
                'resetUrl' => $resetUrl,
            ], function ($message) use ($email) {
                $message->to($email)->subject('ClearanceMS Password Reset');
            });

            if (app()->environment('local') && config('mail.default') === 'log') {
                $localResetUrl = $resetUrl;
            }
        }

        return back()->with('recovery_status', 'If that email belongs to this portal, a password reset link has been prepared.')
            ->with('local_reset_url', $localResetUrl);
    }

    public function showReset(Request $request, string $portal, string $token): View
    {
        $config = $this->portalConfig($portal);

        return view('auth.account-reset', [
            'portal' => $portal,
            'portalName' => $config['name'],
            'loginRoute' => $config['loginRoute'],
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request, string $portal): RedirectResponse
    {
        $config = $this->portalConfig($portal);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);
        $email = strtolower(trim($data['email']));
        $reset = DB::table('account_password_resets')
            ->where('portal', $portal)->where('email', $email)->first();

        if (! $reset || now()->greaterThan($reset->expires_at)
            || ! hash_equals($reset->token_hash, hash('sha256', $data['token']))) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'This password reset link is invalid or has expired.']);
        }

        DB::transaction(function () use ($config, $portal, $email, $data) {
            DB::table($config['table'])->whereRaw('LOWER(email) = ?', [$email])
                ->update(['password' => Hash::make($data['password'])]);
            DB::table('account_password_resets')->where('portal', $portal)->where('email', $email)->delete();
        });

        AuditLogger::record('password_reset.completed', $config['guard'], null, $config['table'], null, [
            'identifier_hash' => hash('sha256', $email),
        ]);

        return redirect()->route($config['loginRoute'])
            ->with('flash', ['type' => 'success', 'message' => 'Your password has been reset. You may now sign in.']);
    }

    private function portalConfig(string $portal): array
    {
        $portals = [
            'main-admin' => ['name' => 'Main Administrator', 'loginRoute' => 'login', 'table' => 'main_admin', 'guard' => 'admin'],
            'student' => ['name' => 'Student', 'loginRoute' => 'student.login', 'table' => 'student_account', 'guard' => 'student'],
            'instructor' => ['name' => 'Instructor', 'loginRoute' => 'instructor.login', 'table' => 'instructor_account', 'guard' => 'instructor'],
            'office' => ['name' => 'Office Personnel', 'loginRoute' => 'office.login', 'table' => 'admin_personnel', 'guard' => 'office'],
            'treasurer' => ['name' => 'Treasurer', 'loginRoute' => 'treasurer.login', 'table' => 'treasurers', 'guard' => 'treasurer'],
            'registrar' => ['name' => 'Registrar', 'loginRoute' => 'registrar.login', 'table' => 'registrar', 'guard' => 'registrar'],
        ];

        abort_unless(isset($portals[$portal]), 404);

        return $portals[$portal];
    }
}
