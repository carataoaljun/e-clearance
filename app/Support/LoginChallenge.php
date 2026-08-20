<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The six-digit code a portal mails out when an account signs in from a device
 * it has not verified before. Holds only the mechanics — generating, mailing,
 * storing and checking the code. Which portal is asking, and what happens once
 * the code checks out, belong to Concerns\VerifiesNewDevices.
 *
 * The challenge lives in the session, never in the database, and the plain code
 * is never stored: only its hash, an expiry, and an attempt counter.
 */
final class LoginChallenge
{
    /** Outcomes of send(); anything but 'sent' means the login cannot continue. */
    public const SENT = 'sent';

    public const NO_EMAIL = 'no_email';

    public const FAILED = 'failed';

    public static function sessionKey(string $guard): string
    {
        return "login_challenge_{$guard}";
    }

    /** @return array<string, mixed>|null */
    public static function pending(Request $request, string $guard): ?array
    {
        $challenge = $request->session()->get(self::sessionKey($guard));

        return is_array($challenge) ? $challenge : null;
    }

    /**
     * Mail a fresh code and park the challenge in the session.
     *
     * @param  array{id: string|int, email: ?string, name: string, remember: bool}  $account
     * @return string one of SENT, NO_EMAIL, FAILED
     */
    public static function send(
        Request $request,
        string $guard,
        string $panel,
        array $account,
        bool $resend = false,
    ): string {
        $email = strtolower(trim((string) ($account['email'] ?? '')));

        if ($email === '') {
            $request->session()->forget(self::sessionKey($guard));
            AuditLogger::record('authentication.mfa_unavailable', $guard, null, null, $account['id'] ?? null, [
                'reason' => 'no_email_on_file',
            ]);

            return self::NO_EMAIL;
        }

        $code = (string) random_int(100000, 999999);
        $lifetime = self::lifetimeSeconds();

        try {
            Mail::send('emails.login-device-code', [
                'accountName' => $account['name'] ?? $panel,
                'panelName' => $panel,
                'code' => $code,
                'deviceLabel' => TrustedDevice::label($request),
                'ipAddress' => (string) $request->ip(),
                'expiresInMinutes' => (int) ceil($lifetime / 60),
                'trustedForDays' => TrustedDevice::lifetimeDays(),
            ], function ($message) use ($email, $panel) {
                $message->to($email)->subject("Your MCC Clearance {$panel} sign-in code");
            });
        } catch (Throwable $exception) {
            report($exception);
            $request->session()->forget(self::sessionKey($guard));

            return self::FAILED;
        }

        $request->session()->put(self::sessionKey($guard), [
            'account_id' => $account['id'],
            'email' => $email,
            'remember' => (bool) ($account['remember'] ?? false),
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addSeconds($lifetime)->timestamp,
            'attempts' => 0,
            // Mirrors the password-recovery flow: with no real mailer running
            // there is no inbox to read the code from, so hand it back to the
            // login page. Never set anywhere but local development.
            'local_code' => self::echoesCodeLocally() ? $code : null,
        ]);

        AuditLogger::record('authentication.mfa_challenge_sent', $guard, null, null, $account['id'] ?? null, [
            'resend' => $resend,
            'expires_in_seconds' => $lifetime,
            'device' => TrustedDevice::label($request),
        ]);

        return self::SENT;
    }

    /**
     * Check a submitted code, consuming the challenge on success so it cannot
     * be replayed.
     *
     * @return array<string, mixed> the challenge that was just satisfied
     *
     * @throws ValidationException
     */
    public static function verify(Request $request, string $guard, string $code): array
    {
        $key = self::sessionKey($guard);
        $challenge = self::pending($request, $guard);

        if ($challenge === null || ($challenge['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget($key);

            throw ValidationException::withMessages([
                'verification_code' => 'This sign-in code has expired. Please enter your password again.',
            ]);
        }

        if (! Hash::check($code, (string) ($challenge['code_hash'] ?? ''))) {
            $attempts = (int) ($challenge['attempts'] ?? 0) + 1;
            $challenge['attempts'] = $attempts;
            $request->session()->put($key, $challenge);

            AuditLogger::record('authentication.mfa_failed', $guard, null, null, $challenge['account_id'] ?? null, [
                'attempts' => $attempts,
            ]);

            if ($attempts >= self::maxAttempts()) {
                $request->session()->forget($key);
                AuditLogger::record('authentication.mfa_locked', $guard, null, null, $challenge['account_id'] ?? null);

                throw ValidationException::withMessages([
                    'verification_code' => 'Too many incorrect codes. Please enter your password again.',
                ]);
            }

            throw ValidationException::withMessages([
                'verification_code' => 'The verification code is incorrect.',
            ]);
        }

        $request->session()->forget($key);

        return $challenge;
    }

    public static function forget(Request $request, string $guard): void
    {
        $request->session()->forget(self::sessionKey($guard));
    }

    /** The plain code, but only when local development has no mailer to send it. */
    public static function localCode(Request $request, string $guard): ?string
    {
        if (! self::echoesCodeLocally()) {
            return null;
        }

        $code = self::pending($request, $guard)['local_code'] ?? null;

        return is_string($code) ? $code : null;
    }

    private static function echoesCodeLocally(): bool
    {
        return app()->environment('local') && config('mail.default') === 'log';
    }

    private static function lifetimeSeconds(): int
    {
        return max(60, (int) config('login_security.otp_lifetime_seconds', 600));
    }

    private static function maxAttempts(): int
    {
        return max(1, (int) config('login_security.otp_max_attempts', 5));
    }
}
