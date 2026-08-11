<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class LoginSecurity
{
    private string $identifierHash;

    private string $accountKey;

    private string $contextKey;

    private function __construct(
        private readonly Request $request,
        private readonly string $guard,
        string $identifier,
    ) {
        $normalized = mb_strtolower(trim($identifier));
        $this->identifierHash = hash('sha256', $normalized);
        $this->accountKey = "login-security:account:{$guard}:{$this->identifierHash}";
        $this->contextKey = "login-security:context:{$guard}:{$this->identifierHash}:".self::requestContextHash($request);
    }

    public static function for(Request $request, string $guard, string $identifier): self
    {
        return new self($request, $guard, $identifier);
    }

    public static function requestContextHash(Request $request): string
    {
        return hash('sha256', implode('|', [
            (string) $request->ip(),
            (string) $request->userAgent(),
            (string) $request->header('Accept-Language'),
            (string) $request->header('Sec-CH-UA-Platform'),
        ]));
    }

    public function assertNotLocked(string $errorField): void
    {
        if (! $this->isLocked()) {
            return;
        }

        $this->requireCaptchaForSession();
        $seconds = max(
            RateLimiter::availableIn($this->accountKey),
            RateLimiter::availableIn($this->contextKey),
        );

        AuditLogger::record('authentication.blocked', $this->guard, metadata: [
            'guard' => $this->guard,
            'identifier_hash' => $this->identifierHash,
            'retry_after_seconds' => $seconds,
            'account_attempts' => RateLimiter::attempts($this->accountKey),
            'context_attempts' => RateLimiter::attempts($this->contextKey),
        ]);

        throw ValidationException::withMessages([
            $errorField => 'Too many failed login attempts. Try again in '.max(1, (int) ceil($seconds / 60)).' minute(s).',
        ]);
    }

    public function assertCaptcha(Request $request, string $errorField): void
    {
        if (! $this->captchaRequired()) {
            return;
        }

        $challenge = $request->session()->pull($this->captchaChallengeKey());
        $answer = mb_strtoupper(trim((string) $request->input('captcha_answer')));
        $valid = is_array($challenge)
            && ($challenge['expires_at'] ?? 0) >= now()->timestamp
            && is_string($challenge['answer_hash'] ?? null)
            && hash_equals($challenge['answer_hash'], $this->hashCaptchaAnswer($answer));

        if ($valid) {
            return;
        }

        AuditLogger::record('authentication.captcha_failed', $this->guard, metadata: [
            'guard' => $this->guard,
            'identifier_hash' => $this->identifierHash,
        ]);

        $this->fail($errorField, 'captcha');
    }

    public function fail(string $errorField, string $reason = 'credentials'): never
    {
        $decay = max(60, (int) config('login_security.lockout_seconds', 900));
        $accountAttempts = RateLimiter::hit($this->accountKey, $decay);
        $contextAttempts = RateLimiter::hit($this->contextKey, $decay);

        if ($this->captchaRequired()) {
            $this->requireCaptchaForSession();
        }

        $metadata = [
            'guard' => $this->guard,
            'identifier_hash' => $this->identifierHash,
            'reason' => $reason,
            'account_attempts' => $accountAttempts,
            'context_attempts' => $contextAttempts,
            'captcha_required' => $this->captchaRequired(),
        ];
        AuditLogger::record('authentication.failed', $this->guard, metadata: $metadata);

        if ($this->isLocked()) {
            $lockoutMinutes = max(1, (int) ceil($decay / 60));
            AuditLogger::record('authentication.locked', $this->guard, metadata: $metadata + [
                'lockout_seconds' => $decay,
            ]);

            throw ValidationException::withMessages([
                $errorField => "Too many failed login attempts. This login is locked for {$lockoutMinutes} minutes.",
            ]);
        }

        $message = $reason === 'captcha'
            ? 'The security code is missing, expired, or incorrect. Please try a new code.'
            : 'The credentials you entered are incorrect.';

        throw ValidationException::withMessages([$errorField => $message]);
    }

    public function clear(): void
    {
        RateLimiter::clear($this->accountKey);
        RateLimiter::clear($this->contextKey);
        $this->request->session()->forget([
            $this->captchaRequiredKey(),
            $this->captchaChallengeKey(),
        ]);
    }

    public function captchaRequired(): bool
    {
        $threshold = max(1, (int) config('login_security.captcha_after', 3));

        return (bool) $this->request->session()->get($this->captchaRequiredKey(), false)
            || RateLimiter::attempts($this->accountKey) >= $threshold
            || RateLimiter::attempts($this->contextKey) >= $threshold;
    }

    public static function issueCaptcha(Request $request, string $guard): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $answer = '';
        for ($index = 0; $index < 5; $index++) {
            $answer .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        $request->session()->put("login_security.challenge.{$guard}", [
            'answer_hash' => self::captchaHash($answer),
            'expires_at' => now()->addSeconds(max(60, (int) config('login_security.captcha_lifetime_seconds', 300)))->timestamp,
        ]);

        return $answer;
    }

    private function isLocked(): bool
    {
        $maximum = max(1, (int) config('login_security.lockout_after', 5));

        return RateLimiter::tooManyAttempts($this->accountKey, $maximum)
            || RateLimiter::tooManyAttempts($this->contextKey, $maximum);
    }

    private function requireCaptchaForSession(): void
    {
        $this->request->session()->put($this->captchaRequiredKey(), true);
    }

    private function captchaRequiredKey(): string
    {
        return "login_security.captcha.{$this->guard}";
    }

    private function captchaChallengeKey(): string
    {
        return "login_security.challenge.{$this->guard}";
    }

    private function hashCaptchaAnswer(string $answer): string
    {
        return self::captchaHash($answer);
    }

    private static function captchaHash(string $answer): string
    {
        return hash_hmac('sha256', mb_strtoupper(trim($answer)), (string) config('app.key'));
    }
}
