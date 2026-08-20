<?php

namespace Tests\Unit;

use App\Support\DeviceFingerprint;
use PHPUnit\Framework\TestCase;

/**
 * Match order is the whole risk here: every Chromium browser also claims
 * "Chrome" and the Android wrapper also claims Chrome, so a careless parser
 * reports everything as Chrome on Android.
 */
class DeviceFingerprintTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function agents(): array
    {
        return [
            'windows chrome' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
                'Chrome', 'Windows', 'desktop',
            ],
            'edge is not chrome' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36 Edg/126.0',
                'Microsoft Edge', 'Windows', 'desktop',
            ],
            'android phone' => [
                'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Mobile Safari/537.36',
                'Chrome', 'Android', 'mobile',
            ],
            'the student android wrapper wins over its webview browser' => [
                'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Mobile Safari/537.36 MCCStudentAndroid/1.4',
                'MCC Student App', 'Android', 'app',
            ],
            'iphone safari' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
                'Safari', 'iOS', 'mobile',
            ],
            'ipad is a tablet' => [
                'Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/604.1',
                'Safari', 'iPadOS', 'tablet',
            ],
            'macos firefox' => [
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:127.0) Gecko/20100101 Firefox/127.0',
                'Firefox', 'macOS', 'desktop',
            ],
            'crawler' => [
                'Googlebot/2.1 (+http://www.google.com/bot.html)',
                'Googlebot/2.1', 'Automated client', 'bot',
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('agents')]
    public function test_it_reads_browser_platform_and_category(
        string $agent,
        string $browser,
        string $platform,
        string $category,
    ): void {
        $device = DeviceFingerprint::describe($agent);

        $this->assertSame($browser, $device['browser']);
        $this->assertSame($platform, $device['platform']);
        $this->assertSame($category, $device['category']);
        $this->assertContains($category, DeviceFingerprint::CATEGORIES);
    }

    public function test_a_missing_user_agent_is_reported_as_unknown_rather_than_guessed(): void
    {
        foreach ([null, '', '   '] as $agent) {
            $device = DeviceFingerprint::describe($agent);

            $this->assertSame('unknown', $device['category']);
            $this->assertSame('Unknown client', $device['label']);
        }
    }

    public function test_it_summarizes_as_browser_on_platform(): void
    {
        $this->assertSame(
            'Chrome on Windows',
            DeviceFingerprint::summarize('Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/126.0 Safari/537.36'),
        );
    }
}
