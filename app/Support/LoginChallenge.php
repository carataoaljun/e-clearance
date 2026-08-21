<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The six-digit code a portal mails out when an account signs in from a device
 * it has not verified before. Holds only the mechanics — generating, mailing,
 * storing and checking the code. Which portal is asking, and what happens once
 * the code checks out, belong to Concerns\VerifiesNewDevices.
 *
 * The challenge lives in the session, never in the database, and the plain code
 * is never stored anywhere, in any environment — only its hash, an expiry, and
 * an attempt counter. Earlier code echoed the plain digits back onto the login
 * page when the app ran locally without real SMTP, on the theory that a
 * developer with no mailer configured still needed to see the code. That was a
 * genuine leak: Laravel's own default mailer is "log" (env('MAIL_MAILER',
 * 'log')), so any environment left unconfigured — a fresh clone, a broken SMTP
 * credential — silently fell back to it, and the code then rendered as plain
 * HTML for anyone who loaded the page, no email access required. A developer
 * testing with the "log" mailer already gets the full rendered email, code
 * included, in storage/logs/laravel.log, which needs filesystem access to
 * read, not just a browser — so nothing is lost by never rendering it into
 * the response.
 *
 * Brute-forcing the code itself is bounded two ways. Each individual code
 * accepts at most maxAttempts() wrong guesses before it is discarded, but that
 * counter resets to zero on every "Resend code", so on its own it caps
 * nothing across a longer attack. The real ceiling is an account-level lock,
 * independent of resend and of the per-code counter: accountLockout() tracks
 * total wrong guesses against this (guard, account) pair, sustained across as
 * many codes as get requested, and once it trips, send() itself refuses to
 * issue another code until the lock expires.
 */
final class LoginChallenge
{
    /** Outcomes of send(); anything but 'sent' means the login cannot continue. */
    public const SENT = 'sent';

    public const NO_EMAIL = 'no_email';

    public const FAILED = 'failed';

    public const LOCKED = 'locked';

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
     * @return string one of SENT, NO_EMAIL, FAILED, LOCKED
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

        $accountId = $account['id'] ?? '';

        if (self::accountLockout($guard, $accountId)->tooManyAttempts()) {
            $request->session()->forget(self::sessionKey($guard));
            AuditLogger::record('authentication.mfa_locked', $guard, null, null, $accountId, [
                'reason' => 'locked_before_send',
            ]);

            return self::LOCKED;
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
            'account_id' => $accountId,
            'email' => $email,
            'remember' => (bool) ($account['remember'] ?? false),
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addSeconds($lifetime)->timestamp,
            'attempts' => 0,
        ]);

        AuditLogger::record('authentication.mfa_challenge_sent', $guard, null, null, $accountId, [
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

        $accountId = $challenge['account_id'] ?? '';
        $lockout = self::accountLockout($guard, $accountId);

        if ($lockout->tooManyAttempts()) {
            $request->session()->forget($key);
            AuditLogger::record('authentication.mfa_locked', $guard, null, null, $accountId, [
                'reason' => 'locked_before_verify',
            ]);

            throw ValidationException::withMessages([
                'verification_code' => 'Too many incorrect codes. Please enter your password again.',
            ]);
        }

        if (! Hash::check($code, (string) ($challenge['code_hash'] ?? ''))) {
            // Two counters, deliberately separate. The per-challenge count below
            // only ever discards THIS code -- "Resend" hands out a brand new one
            // with its own count reset to zero. The account-level hit above
            // survives every resend, so repeatedly cycling codes cannot be used
            // to reset an attacker's remaining guesses.
            $lockout->hit();

            $attempts = (int) ($challenge['attempts'] ?? 0) + 1;
            $challenge['attempts'] = $attempts;
            $request->session()->put($key, $challenge);

            AuditLogger::record('authentication.mfa_failed', $guard, null, null, $accountId, [
                'attempts' => $attempts,
                'account_attempts' => $lockout->attempts(),
            ]);

            if ($lockout->tooManyAttempts()) {
                $request->session()->forget($key);
                AuditLogger::record('authentication.mfa_locked', $guard, null, null, $accountId, [
                    'reason' => 'locked_after_verify',
                ]);

                throw ValidationException::withMessages([
                    'verification_code' => 'Too many incorrect codes. Please enter your password again.',
                ]);
            }

            if ($attempts >= self::maxAttempts()) {
                $request->session()->forget($key);
                AuditLogger::record('authentication.mfa_locked', $guard, null, null, $accountId, [
                    'reason' => 'code_exhausted',
                ]);

                throw ValidationException::withMessages([
                    'verification_code' => 'Too many incorrect codes. Please enter your password again.',
                ]);
            }

            throw ValidationException::withMessages([
                'verification_code' => 'The verification code is incorrect.',
            ]);
        }

        $lockout->clear();
        $request->session()->forget($key);

        return $challenge;
    }

    public static function forget(Request $request, string $guard): void
    {
        $request->session()->forget(self::sessionKey($guard));
    }

    private static function accountLockout(string $guard, string|int $accountId): LoginChallengeLockout
    {
        return new LoginChallengeLockout($guard, $accountId);
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

/**
 * A small RateLimiter wrapper scoped to one (guard, account) pair. Kept
 * private to this file — nothing outside LoginChallenge needs to touch it.
 * Unlike the per-challenge attempt count, this key is never cleared by
 * issuing a new code, only by a correct one or by its own decay window.
 */
final class LoginChallengeLockout
{
    private readonly string $key;

    public function __construct(string $guard, string|int $accountId)
    {
        $hash = substr(hash('sha256', (string) $accountId), 0, 20);
        $this->key = "login-otp-lockout:{$guard}:{$hash}";
    }

    public function tooManyAttempts(): bool
    {
        return RateLimiter::tooManyAttempts($this->key, self::maxAttempts());
    }

    public function attempts(): int
    {
        return RateLimiter::attempts($this->key);
    }

    public function hit(): void
    {
        RateLimiter::hit($this->key, self::decaySeconds());
    }

    public function clear(): void
    {
        RateLimiter::clear($this->key);
    }

    private static function maxAttempts(): int
    {
        return max(1, (int) config('login_security.otp_account_lockout_after', 8));
    }

    private static function decaySeconds(): int
    {
        return max(60, (int) config('login_security.lockout_seconds', 900));
    }
}
