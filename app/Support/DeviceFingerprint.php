<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Turns the raw user_agent already stored on every security_audit_logs row into
 * something the Main Admin activity page can read: a browser, a platform, and a
 * device category.
 *
 * The string is all there is to work with — the audit table records no device
 * identifier — so match order matters. Every Chromium browser also claims
 * "Chrome" and every in-app WebView also claims a browser, so the more specific
 * token has to be tested first or everything collapses into "Chrome".
 */
final class DeviceFingerprint
{
    /** Every category describe() can return; also the values the activity filter accepts. */
    public const CATEGORIES = ['desktop', 'mobile', 'tablet', 'app', 'bot', 'unknown'];

    /** Marker the Android WebView wrapper in mobile/student-android appends to its user agent. */
    private const ANDROID_APP = 'MCCStudentAndroid/';

    /** Browser tokens, most specific first. */
    private const BROWSERS = [
        'Edg' => 'Microsoft Edge',
        'OPR' => 'Opera',
        'Opera' => 'Opera',
        'SamsungBrowser' => 'Samsung Internet',
        'YaBrowser' => 'Yandex Browser',
        'Vivaldi' => 'Vivaldi',
        'Brave' => 'Brave',
        'CriOS' => 'Chrome',
        'FxiOS' => 'Firefox',
        'Firefox' => 'Firefox',
        'Chrome' => 'Chrome',
        'Safari' => 'Safari',
        'Trident' => 'Internet Explorer',
        'MSIE' => 'Internet Explorer',
    ];

    /** Platform tokens, most specific first. */
    private const PLATFORMS = [
        'CrOS' => 'ChromeOS',
        'Android' => 'Android',
        'iPhone' => 'iOS',
        'iPad' => 'iPadOS',
        'iPod' => 'iOS',
        'Windows Phone' => 'Windows Phone',
        'Windows' => 'Windows',
        'Macintosh' => 'macOS',
        'Mac OS X' => 'macOS',
        'Ubuntu' => 'Ubuntu',
        'Linux' => 'Linux',
    ];

    /**
     * @return array{browser: string, platform: string, category: string, label: string, icon: string}
     */
    public static function describe(?string $userAgent): array
    {
        $agent = trim((string) $userAgent);

        if ($agent === '') {
            return self::compose('Unknown client', 'Unknown platform', 'unknown');
        }

        if (str_contains($agent, self::ANDROID_APP)) {
            return self::compose('MCC Student App', self::platform($agent) ?: 'Android', 'app');
        }

        if (preg_match('/bot|crawler|spider|curl|wget|python-requests|postman|httpclient/i', $agent) === 1) {
            return self::compose(self::firstWord($agent), 'Automated client', 'bot');
        }

        return self::compose(
            self::browser($agent) ?: 'Unknown client',
            self::platform($agent) ?: 'Unknown platform',
            self::category($agent),
        );
    }

    /** Short "Chrome on Windows" style summary, handy in a table cell. */
    public static function summarize(?string $userAgent): string
    {
        return self::describe($userAgent)['label'];
    }

    private static function browser(string $agent): ?string
    {
        foreach (self::BROWSERS as $token => $name) {
            if (str_contains($agent, $token)) {
                return $name;
            }
        }

        return null;
    }

    private static function platform(string $agent): ?string
    {
        foreach (self::PLATFORMS as $token => $name) {
            if (str_contains($agent, $token)) {
                return $name;
            }
        }

        return null;
    }

    private static function category(string $agent): string
    {
        return match (true) {
            (bool) preg_match('/iPad|Tablet|PlayBook|Silk/i', $agent) => 'tablet',
            (bool) preg_match('/Mobi|iPhone|iPod|Android.*Mobile|Windows Phone/i', $agent) => 'mobile',
            str_contains($agent, 'Android') => 'tablet',
            default => 'desktop',
        };
    }

    private static function firstWord(string $agent): string
    {
        return Str::limit(Str::before($agent, ' ') ?: $agent, 40);
    }

    /**
     * @return array{browser: string, platform: string, category: string, label: string, icon: string}
     */
    private static function compose(string $browser, string $platform, string $category): array
    {
        return [
            'browser' => $browser,
            'platform' => $platform,
            'category' => $category,
            'label' => $platform === 'Unknown platform' ? $browser : "{$browser} on {$platform}",
            'icon' => match ($category) {
                'mobile' => 'bi bi-phone',
                'tablet' => 'bi bi-tablet',
                'app' => 'bi bi-android2',
                'bot' => 'bi bi-robot',
                'desktop' => 'bi bi-laptop',
                default => 'bi bi-question-circle',
            },
        ];
    }
}
