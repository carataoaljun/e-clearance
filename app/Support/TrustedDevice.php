<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Throwable;

/**
 * Remembers which browsers an account has already cleared by email code, so the
 * one-time code is only demanded the first time that account signs in from a
 * given device.
 *
 * The register lives in a single encrypted cookie rather than a table on
 * purpose: deploys never run `artisan migrate` (see CLAUDE.md), and a table
 * that never gets created would leave every portal mailing a code on every
 * single login. Laravel encrypts and signs cookies with APP_KEY, so a browser
 * cannot forge an entry for itself.
 *
 * One cookie holds every portal the browser has verified, keyed by guard and
 * account, because ids collide across the seven portal tables.
 */
final class TrustedDevice
{
    public const COOKIE = 'mcc_verified_devices';

    /** How many (guard, account) pairs one browser may stay verified for. */
    private const MAX_ENTRIES = 12;

    public static function trusted(Request $request, string $guard, string|int $accountId): bool
    {
        $entry = self::register($request)[self::key($guard, $accountId)] ?? null;

        return is_array($entry)
            && (int) ($entry['exp'] ?? 0) > now()->timestamp
            && hash_equals(self::fingerprint($request), (string) ($entry['fp'] ?? ''));
    }

    /** Queue the cookie that lets this browser skip the code next time. */
    public static function remember(Request $request, string $guard, string|int $accountId): void
    {
        $register = self::prune(self::register($request));
        unset($register[self::key($guard, $accountId)]);

        $register[self::key($guard, $accountId)] = [
            'fp' => self::fingerprint($request),
            'exp' => now()->addDays(self::lifetimeDays())->timestamp,
        ];

        if (count($register) > self::MAX_ENTRIES) {
            $register = array_slice($register, -self::MAX_ENTRIES, preserve_keys: true);
        }

        Cookie::queue(Cookie::make(
            name: self::COOKIE,
            value: (string) json_encode($register),
            minutes: self::lifetimeDays() * 24 * 60,
            httpOnly: true,
            sameSite: 'Lax',
        ));
    }

    /** "Chrome on Windows" style label for the notification email. */
    public static function label(Request $request): string
    {
        return DeviceFingerprint::summarize($request->userAgent());
    }

    public static function lifetimeDays(): int
    {
        return max(1, (int) config('login_security.trusted_device_days', 30));
    }

    /**
     * Binds the cookie to the browser that earned it. Built from the parsed
     * description rather than the raw user agent so a routine browser update
     * does not read as a brand new device.
     */
    private static function fingerprint(Request $request): string
    {
        $device = DeviceFingerprint::describe($request->userAgent());

        return substr(hash_hmac('sha256', implode('|', [
            $device['browser'],
            $device['platform'],
            $device['category'],
        ]), (string) config('app.key')), 0, 16);
    }

    /** @return array<string, array{fp: string, exp: int}> */
    private static function register(Request $request): array
    {
        $raw = $request->cookie(self::COOKIE);

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, array{fp: string, exp: int}> */
    private static function prune(array $register): array
    {
        return array_filter(
            $register,
            fn ($entry) => is_array($entry) && (int) ($entry['exp'] ?? 0) > now()->timestamp,
        );
    }

    private static function key(string $guard, string|int $accountId): string
    {
        return $guard.':'.substr(hash('sha256', (string) $accountId), 0, 16);
    }
}
