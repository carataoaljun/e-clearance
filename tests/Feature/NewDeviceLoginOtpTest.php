<?php

namespace Tests\Feature;

use App\Models\AdminPersonnel;
use App\Models\Instructor;
use App\Models\MainAdmin;
use App\Models\Registrar;
use App\Models\SecurityAuditLog;
use App\Models\StudentAccount;
use App\Models\Treasurer;
use App\Support\AuditLogger;
use App\Support\LoginChallenge;
use App\Support\TrustedDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use ReflectionProperty;
use Tests\TestCase;

class NewDeviceLoginOtpTest extends TestCase
{
    use RefreshDatabase;

    /** A user agent no account in these tests has ever been verified on. */
    private const DESKTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

    private const PHONE = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Mobile Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        $tableAvailable = new ReflectionProperty(AuditLogger::class, 'tableAvailable');
        $tableAvailable->setValue(null, null);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    public static function portals(): array
    {
        // guard, login route, otp verify route, dashboard route, login page
        return [
            'student' => ['student', 'student.login.submit', 'student.login.otp.verify', 'student.dashboard', 'student.login'],
            'instructor' => ['instructor', 'instructor.login.submit', 'instructor.login.otp.verify', 'instructor.dashboard', 'instructor.login'],
            'office' => ['office', 'office.login.submit', 'office.login.otp.verify', 'office.dashboard', 'office.login'],
            'registrar' => ['registrar', 'registrar.login.submit', 'registrar.login.otp.verify', 'registrar.dashboard', 'registrar.login'],
            'treasurer' => ['treasurer', 'treasurer.login.submit', 'treasurer.login.otp.verify', 'treasurer.dashboard', 'treasurer.login'],
            'admin' => ['admin', 'login.post', 'login.otp.verify', 'dashboard', 'login'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('portals')]
    public function test_a_first_time_device_is_held_at_an_emailed_code(
        string $guard,
        string $loginRoute,
        string $otpRoute,
        string $home,
    ): void {
        $account = $this->createAccount($guard);

        $this->withServerVariables(['HTTP_USER_AGENT' => self::DESKTOP])
            ->post(route($loginRoute), $this->credentials($guard))
            ->assertSessionHas(LoginChallenge::sessionKey($guard))
            ->assertSessionHas('status');

        // The password was right, but the session must stay shut until the code lands.
        $this->assertGuest($guard);
        $this->assertDatabaseHas('security_audit_logs', [
            'event' => 'authentication.mfa_challenge_sent',
            'actor_guard' => $guard,
        ]);

        $sent = $this->sentMessages();
        $this->assertCount(1, $sent, 'A new device must trigger exactly one email.');
        $this->assertSame($account->email, $sent[0]->getEnvelope()->getRecipients()[0]->getAddress());
        // Decoded first: quoted-printable soft breaks can split the digits.
        $this->assertMatchesRegularExpression(
            '/letter-spacing:8px;color:#075bea">\d{6}</',
            quoted_printable_decode($sent[0]->toString()),
        );

        $challenge = session(LoginChallenge::sessionKey($guard));
        $challenge['code_hash'] = Hash::make('418327');

        $this->withSession([LoginChallenge::sessionKey($guard) => $challenge])
            ->post(route($otpRoute), ['verification_code' => '418327'])
            ->assertRedirect(route($home))
            ->assertSessionHas('login_success');

        $this->assertAuthenticatedAs($account, $guard);
        $this->assertNull(session(LoginChallenge::sessionKey($guard)));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('portals')]
    public function test_a_wrong_code_never_opens_the_session(
        string $guard,
        string $loginRoute,
        string $otpRoute,
    ): void {
        $this->createAccount($guard);

        $this->withServerVariables(['HTTP_USER_AGENT' => self::DESKTOP])
            ->post(route($loginRoute), $this->credentials($guard));

        $challenge = session(LoginChallenge::sessionKey($guard));
        $challenge['code_hash'] = Hash::make('418327');

        $this->withSession([LoginChallenge::sessionKey($guard) => $challenge])
            ->post(route($otpRoute), ['verification_code' => '000000'])
            ->assertSessionHasErrors('verification_code');

        $this->assertGuest($guard);
    }

    /**
     * Each portal's login page has to resolve its own three code routes. A
     * wrong route name is a runtime Blade error, so every portal renders here.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('portals')]
    public function test_every_login_page_renders_its_own_code_panel(
        string $guard,
        string $loginRoute,
        string $otpRoute,
        string $home,
        string $loginPage,
    ): void {
        $this->createAccount($guard);
        $this->post(route($loginRoute), $this->credentials($guard));

        $this->get(route($loginPage))
            ->assertOk()
            ->assertSee('Verify This Device')
            ->assertSee('data-panel="device-otp"', false)
            ->assertSee(route($otpRoute), false)
            ->assertSee(route(str_replace('.verify', '.resend', $otpRoute)), false)
            ->assertSee(route(str_replace('.verify', '.cancel', $otpRoute)), false);
    }

    public function test_a_verified_device_signs_in_again_without_a_code(): void
    {
        $student = $this->createAccount('student');

        $verified = $this->withServerVariables(['HTTP_USER_AGENT' => self::DESKTOP])
            ->loginThroughDeviceCode('student', 'student.login.submit', $this->credentials('student'), 'student.login.otp.verify');
        $verified->assertRedirect(route('student.dashboard'));

        $deviceCookie = $this->verifiedDeviceCookie($verified);
        $this->assertNotNull($deviceCookie, 'Verifying the code should hand the browser a device cookie.');

        $this->post(route('student.logout'));

        $this->withServerVariables(['HTTP_USER_AGENT' => self::DESKTOP])
            ->withCookie(TrustedDevice::COOKIE, $deviceCookie)
            ->post(route('student.login.submit'), $this->credentials('student'))
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionMissing(LoginChallenge::sessionKey('student'));

        $this->assertAuthenticatedAs($student, 'student');
    }

    public function test_the_same_cookie_on_a_different_device_is_challenged_again(): void
    {
        $this->createAccount('student');

        $verified = $this->withServerVariables(['HTTP_USER_AGENT' => self::DESKTOP])
            ->loginThroughDeviceCode('student', 'student.login.submit', $this->credentials('student'), 'student.login.otp.verify');
        $deviceCookie = $this->verifiedDeviceCookie($verified);

        $this->post(route('student.logout'));

        // Same cookie, different browser: the entry no longer matches its fingerprint.
        $this->withServerVariables(['HTTP_USER_AGENT' => self::PHONE])
            ->withCookie(TrustedDevice::COOKIE, $deviceCookie)
            ->post(route('student.login.submit'), $this->credentials('student'))
            ->assertSessionHas(LoginChallenge::sessionKey('student'));

        $this->assertGuest('student');
    }

    public function test_a_device_verified_for_one_account_does_not_cover_another(): void
    {
        $this->createAccount('student');
        StudentAccount::create([
            'student_id' => 'DEVICE-STUDENT-002',
            'firstname' => 'Second',
            'lastname' => 'Student',
            'email' => 'device-student-2@example.test',
            'password' => 'Strong-Password-123!',
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'A',
            'student_type' => 'Regular',
        ]);

        $verified = $this->withServerVariables(['HTTP_USER_AGENT' => self::DESKTOP])
            ->loginThroughDeviceCode('student', 'student.login.submit', $this->credentials('student'), 'student.login.otp.verify');
        $deviceCookie = $this->verifiedDeviceCookie($verified);

        $this->post(route('student.logout'));

        $this->withServerVariables(['HTTP_USER_AGENT' => self::DESKTOP])
            ->withCookie(TrustedDevice::COOKIE, $deviceCookie)
            ->post(route('student.login.submit'), [
                'student_id' => 'DEVICE-STUDENT-002',
                'password' => 'Strong-Password-123!',
            ])->assertSessionHas(LoginChallenge::sessionKey('student'));

        $this->assertGuest('student');
    }

    public function test_main_admin_is_challenged_on_every_sign_in_even_from_a_known_device(): void
    {
        $this->createAccount('admin');

        $verified = $this->withServerVariables(['HTTP_USER_AGENT' => self::DESKTOP])
            ->loginThroughDeviceCode('admin', 'login.post', $this->credentials('admin'), 'login.otp.verify');
        $verified->assertRedirect(route('dashboard'));

        $this->assertNull(
            $this->verifiedDeviceCookie($verified),
            'The Main Admin portal must never bank a device.',
        );

        $this->post(route('logout'));

        $this->withServerVariables(['HTTP_USER_AGENT' => self::DESKTOP])
            ->post(route('login.post'), $this->credentials('admin'))
            ->assertSessionHas(LoginChallenge::sessionKey('admin'));

        $this->assertGuest('admin');
    }

    public function test_a_wrong_password_is_rejected_before_any_code_is_sent(): void
    {
        $this->createAccount('student');

        $this->post(route('student.login.submit'), [
            'student_id' => 'DEVICE-STUDENT-001',
            'password' => 'Wrong-Password-123!',
        ])->assertSessionHasErrors('student_id');

        $this->assertNull(session(LoginChallenge::sessionKey('student')));
        $this->assertCount(0, $this->sentMessages());
    }

    public function test_the_code_is_abandoned_after_five_wrong_guesses(): void
    {
        $this->createAccount('student');

        $this->post(route('student.login.submit'), $this->credentials('student'));
        $challenge = session(LoginChallenge::sessionKey('student'));

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withSession([LoginChallenge::sessionKey('student') => $challenge])
                ->post(route('student.login.otp.verify'), ['verification_code' => '000000'])
                ->assertSessionHasErrors('verification_code');

            $challenge['attempts'] = $attempt;
        }

        $this->assertNull(session(LoginChallenge::sessionKey('student')));
        $this->assertGuest('student');
        $this->assertSame(
            1,
            SecurityAuditLog::where('event', 'authentication.mfa_locked')->count(),
        );
    }

    public function test_cancelling_the_code_returns_to_the_login_page(): void
    {
        $this->createAccount('student');

        $this->post(route('student.login.submit'), $this->credentials('student'));
        $this->assertNotNull(session(LoginChallenge::sessionKey('student')));

        $this->post(route('student.login.otp.cancel'))
            ->assertRedirect(route('student.login'));

        $this->assertNull(session(LoginChallenge::sessionKey('student')));
        $this->assertGuest('student');
    }

    public function test_resending_replaces_the_pending_code(): void
    {
        $this->createAccount('student');

        $this->post(route('student.login.submit'), $this->credentials('student'));
        $first = session(LoginChallenge::sessionKey('student'));

        $this->post(route('student.login.otp.resend'))
            ->assertRedirect(route('student.login'))
            ->assertSessionHas('status');

        $second = session(LoginChallenge::sessionKey('student'));
        $this->assertNotSame($first['code_hash'], $second['code_hash']);
        $this->assertSame($first['account_id'], $second['account_id']);

        // The superseded code is dead.
        $this->withSession([LoginChallenge::sessionKey('student') => $second])
            ->post(route('student.login.otp.verify'), ['verification_code' => '111111'])
            ->assertSessionHasErrors('verification_code');
        $this->assertGuest('student');
    }

    /**
     * A prior version of this feature echoed the plain code onto the login
     * page whenever the app ran with no real mailer configured, on the theory
     * that a developer needed to see it somewhere. That was a genuine leak:
     * Laravel's own default mailer is "log" (`env('MAIL_MAILER', 'log')`), so
     * any unconfigured environment fell into it silently, and the code then
     * rendered as visible HTML to anyone who loaded the page — no access to
     * the inbox required. The guarantee this test protects is the outcome,
     * not the removed mechanism: the emailed code must never appear anywhere
     * in a response, regardless of what triggered the original leak.
     */
    public function test_the_emailed_code_never_appears_in_any_response_or_session_value(): void
    {
        $this->createAccount('student');

        $challengeResponse = $this->withServerVariables(['HTTP_USER_AGENT' => self::DESKTOP])
            ->post(route('student.login.submit'), $this->credentials('student'));

        $sent = $this->sentMessages();
        $this->assertCount(1, $sent);
        $body = quoted_printable_decode($sent[0]->toString());
        $this->assertSame(1, preg_match('/letter-spacing:8px;color:#075bea">(\d{6})</', $body, $matches));
        $code = $matches[1];

        // Nothing under any key exposes the plain digits back to the browser.
        foreach ($challengeResponse->getSession()->all() as $value) {
            if (is_string($value)) {
                $this->assertStringNotContainsString($code, $value);
            }
        }
        $challenge = session(LoginChallenge::sessionKey('student'));
        $this->assertIsArray($challenge);
        $this->assertArrayNotHasKey('local_code', $challenge);
        $this->assertStringNotContainsString($code, (string) $challenge['code_hash']);

        // The rendered login page — where the leak actually showed up — must
        // not contain the code, and the removed feature must not resurface.
        $page = $this->get(route('student.login'));
        $page->assertOk();
        $page->assertDontSee($code);
        $page->assertDontSeeText('Local testing code');
        $this->assertStringNotContainsString('local_verification_code', $page->getContent());
    }

    /**
     * The per-code attempt counter alone is not the real defense: "Resend
     * code" hands out a fresh code with its own counter reset to zero, so an
     * attacker who never runs out of resends would never run out of guesses
     * either. The account-level lock in LoginChallengeLockout is what closes
     * that gap — it accumulates wrong guesses across every code the account
     * is issued, and once it trips, send() itself refuses to mail another one.
     */
    public function test_wrong_guesses_lock_the_account_across_resent_codes(): void
    {
        $this->createAccount('student');
        $guard = 'student';
        $key = LoginChallenge::sessionKey($guard);

        $this->post(route('student.login.submit'), $this->credentials('student'));

        // Four wrong guesses against the first code — short of exhausting it
        // (the per-code cap is 5), so the code itself survives.
        $challenge = session($key);
        for ($i = 0; $i < 4; $i++) {
            $this->withSession([$key => $challenge])
                ->post(route('student.login.otp.verify'), ['verification_code' => '000000'])
                ->assertSessionHasErrors('verification_code');
            $challenge = session($key);
        }
        $this->assertSame(4, $challenge['attempts']);

        $this->withSession([$key => $challenge])
            ->post(route('student.login.otp.resend'))
            ->assertRedirect(route('student.login'));
        $resent = session($key);
        $this->assertSame(0, $resent['attempts'], 'Resend must reset the per-code counter.');

        // Four more wrong guesses against the resent code: 4 + 4 = 8, tripping
        // the account-level lock before this second code's own cap of 5 does.
        $challenge = $resent;
        for ($i = 0; $i < 3; $i++) {
            $this->withSession([$key => $challenge])
                ->post(route('student.login.otp.verify'), ['verification_code' => '000000'])
                ->assertSessionHasErrors('verification_code');
            $challenge = session($key);
        }

        $locking = $this->withSession([$key => $challenge])
            ->post(route('student.login.otp.verify'), ['verification_code' => '000000']);
        $locking->assertSessionHasErrors('verification_code');
        $this->assertStringContainsString(
            'Too many incorrect codes',
            $locking->getSession()->get('errors')->first('verification_code'),
        );
        $this->assertNull(session($key), 'The account-level lock must discard the challenge outright.');

        $lockLog = SecurityAuditLog::where('event', 'authentication.mfa_locked')
            ->latest('id')->first();
        $this->assertSame('locked_after_verify', $lockLog->metadata['reason']);

        // Locked out means locked out: even "Resend" is refused, so the
        // attacker cannot buy fresh guesses by cycling codes.
        $sentBefore = $this->sentMessages()->count();
        $this->post(route('student.login.otp.resend'))
            ->assertRedirect(route('student.login'))
            ->assertSessionHasErrors('student_id');
        $this->assertNull(session($key));
        $this->assertCount($sentBefore, $this->sentMessages(), 'A locked account must not receive another code.');
    }

    public function test_an_account_without_an_email_cannot_be_signed_in(): void
    {
        StudentAccount::create([
            'student_id' => 'NO-EMAIL-001',
            'firstname' => 'Emailless',
            'lastname' => 'Student',
            'email' => '',
            'password' => 'Strong-Password-123!',
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'A',
            'student_type' => 'Regular',
        ]);

        $this->post(route('student.login.submit'), [
            'student_id' => 'NO-EMAIL-001',
            'password' => 'Strong-Password-123!',
        ])->assertSessionHasErrors('student_id');

        $this->assertGuest('student');
        $this->assertDatabaseHas('security_audit_logs', [
            'event' => 'authentication.mfa_unavailable',
            'actor_guard' => 'student',
        ]);
    }

    /**
     * Everything the array mailer has captured this test. The codes go out
     * through raw Mail::send(), which Mail::fake() drops without recording,
     * so the transport is the only place they can be inspected.
     *
     * @return \Illuminate\Support\Collection<int, \Symfony\Component\Mailer\SentMessage>
     */
    private function sentMessages(): Collection
    {
        $transport = Mail::mailer()->getSymfonyTransport();

        return $transport instanceof ArrayTransport ? $transport->messages() : collect();
    }

    /** @return array<string, string> */
    private function credentials(string $guard): array
    {
        return match ($guard) {
            'student' => ['student_id' => 'DEVICE-STUDENT-001', 'password' => 'Strong-Password-123!'],
            'instructor' => ['email' => 'device-instructor@example.test', 'password' => 'Strong-Password-123!'],
            'office' => ['login' => 'device-office@example.test', 'password' => 'Strong-Password-123!', 'role' => 'library'],
            'registrar' => ['login' => 'device-registrar@example.test', 'password' => 'Strong-Password-123!'],
            'treasurer' => ['login' => 'device-treasurer@example.test', 'password' => 'Strong-Password-123!', 'treasurer_type' => 'department'],
            'admin' => ['email' => 'device-admin@example.test', 'password' => 'Strong-Password-123!'],
        };
    }

    private function createAccount(string $guard): mixed
    {
        return match ($guard) {
            'student' => StudentAccount::create([
                'student_id' => 'DEVICE-STUDENT-001',
                'firstname' => 'Device',
                'lastname' => 'Student',
                'email' => 'device-student@example.test',
                'password' => 'Strong-Password-123!',
                'program' => 'BSIT',
                'year_level' => '4',
                'section' => 'A',
                'student_type' => 'Regular',
            ]),
            'instructor' => Instructor::create([
                'instructor_id' => 'DEVICE-INSTRUCTOR-001',
                'firstname' => 'Device',
                'lastname' => 'Instructor',
                'email' => 'device-instructor@example.test',
                'password' => 'Strong-Password-123!',
                'department' => 'BSIT',
            ]),
            'office' => AdminPersonnel::create([
                'personnel_id' => 'DEVICE-OFFICE-001',
                'firstname' => 'Device',
                'lastname' => 'Officer',
                'email' => 'device-office@example.test',
                'password' => 'Strong-Password-123!',
                'office' => 'Library',
                'role' => 'library',
            ]),
            'registrar' => Registrar::create([
                'registrar_id' => 'DEVICE-REGISTRAR-001',
                'firstname' => 'Device',
                'lastname' => 'Registrar',
                'email' => 'device-registrar@example.test',
                'password' => 'Strong-Password-123!',
                'role' => 'registrar',
            ]),
            'treasurer' => Treasurer::create([
                'treasurer_id' => 'DEVICE-TREASURER-001',
                'firstname' => 'Device',
                'lastname' => 'Treasurer',
                'email' => 'device-treasurer@example.test',
                'password' => 'Strong-Password-123!',
                'treasurer_type' => 'department',
                'department' => 'BSIT',
            ]),
            'admin' => MainAdmin::create([
                'name' => 'Device Administrator',
                'email' => 'device-admin@example.test',
                'password' => 'Strong-Password-123!',
            ]),
        };
    }
}
