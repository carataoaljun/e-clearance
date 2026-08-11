<?php

namespace Tests\Feature;

use App\Models\MainAdmin;
use App\Models\SecurityAuditLog;
use App\Models\StudentAccount;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use ReflectionProperty;
use Tests\TestCase;

class LoginSecurityAndAdminOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $tableAvailable = new ReflectionProperty(AuditLogger::class, 'tableAvailable');
        $tableAvailable->setValue(null, null);
    }

    public function test_captcha_is_required_after_three_failed_logins_and_failures_are_audited(): void
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->post(route('student.login.submit'), [
                'student_id' => 'CAPTCHA-STUDENT-001',
                'password' => 'Wrong-Password-123!',
            ])->assertSessionHasErrors('student_id');
        }

        $this->assertTrue((bool) session('login_security.captcha.student'));
        $this->get(route('student.login'))
            ->assertOk()
            ->assertSee('Security check: enter the five characters shown');

        $captcha = $this->get(route('auth.captcha', 'student'));
        $captcha->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertIsArray(session('login_security.challenge.student'));

        $this->assertDatabaseCount('security_audit_logs', 3);
        $this->assertSame(
            3,
            SecurityAuditLog::where('event', 'authentication.failed')->count(),
        );
    }

    public function test_account_is_locked_across_changing_ip_contexts_after_five_failures(): void
    {
        foreach (range(1, 5) as $attempt) {
            $response = $this->withServerVariables([
                'REMOTE_ADDR' => "203.0.113.{$attempt}",
                'HTTP_USER_AGENT' => "Login test device {$attempt}",
            ])->post(route('student.login.submit'), [
                'student_id' => 'DISTRIBUTED-ATTACK-001',
                'password' => 'Wrong-Password-123!',
            ]);

            $response->assertSessionHasErrors('student_id');
        }

        $this->assertStringContainsString(
            'locked for 15 minutes',
            session('errors')->first('student_id'),
        );
        $this->assertDatabaseHas('security_audit_logs', [
            'event' => 'authentication.locked',
            'actor_guard' => 'student',
        ]);

        $student = $this->createStudent('DISTRIBUTED-ATTACK-001');
        $this->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.99',
            'HTTP_USER_AGENT' => 'Another device',
        ])->post(route('student.login.submit'), [
            'student_id' => $student->student_id,
            'password' => 'Strong-Student-Password-123!',
        ])->assertSessionHasErrors('student_id');

        $this->assertGuest('student');
        $this->assertDatabaseHas('security_audit_logs', [
            'event' => 'authentication.blocked',
            'actor_guard' => 'student',
        ]);
    }

    public function test_successful_authentication_resets_failure_counters(): void
    {
        $student = $this->createStudent('RESET-FAILURES-001');

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->post(route('student.login.submit'), [
                'student_id' => $student->student_id,
                'password' => 'Wrong-Password-123!',
            ])->assertSessionHasErrors('student_id');
        }

        $this->post(route('student.login.submit'), [
            'student_id' => $student->student_id,
            'password' => 'Strong-Student-Password-123!',
        ])->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($student, 'student');

        $this->post(route('student.logout'));
        $this->post(route('student.login.submit'), [
            'student_id' => $student->student_id,
            'password' => 'Wrong-Password-123!',
        ])->assertSessionHasErrors('student_id');

        $latest = SecurityAuditLog::where('event', 'authentication.failed')->latest('id')->firstOrFail();
        $this->assertSame(1, $latest->metadata['account_attempts']);
        $this->assertFalse((bool) session('login_security.captcha.student', false));
    }

    public function test_main_admin_password_creates_email_code_challenge_before_authentication(): void
    {
        $admin = MainAdmin::create([
            'name' => 'OTP Administrator',
            'email' => 'otp-admin@example.test',
            'password' => 'Strong-Admin-Password-123!',
        ]);

        $this->post(route('login.post'), [
            'email' => $admin->email,
            'password' => 'Strong-Admin-Password-123!',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('admin_login_challenge');

        $this->assertGuest('admin');
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Verify Your Sign-in')
            ->assertSee('Verify &amp; Open Admin Panel', false);

        $challenge = session('admin_login_challenge');
        $challenge['code_hash'] = Hash::make('482731');

        $this->withSession(['admin_login_challenge' => $challenge])
            ->post(route('login.otp.verify'), ['verification_code' => '482731'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('login_success');

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertNull(session('admin_login_challenge'));
        $this->assertDatabaseHas('security_audit_logs', [
            'event' => 'authentication.mfa_verified',
            'actor_guard' => 'admin',
        ]);
    }

    public function test_expired_or_repeated_admin_code_cannot_authenticate(): void
    {
        $admin = MainAdmin::create([
            'name' => 'Expired OTP Administrator',
            'email' => 'expired-otp@example.test',
            'password' => 'Strong-Admin-Password-123!',
        ]);

        $this->withSession(['admin_login_challenge' => [
            'admin_id' => $admin->id,
            'email' => $admin->email,
            'code_hash' => Hash::make('125904'),
            'expires_at' => now()->subSecond()->timestamp,
            'attempts' => 0,
        ]])->post(route('login.otp.verify'), [
            'verification_code' => '125904',
        ])->assertSessionHasErrors('verification_code');

        $this->assertGuest('admin');
        $this->assertNull(session('admin_login_challenge'));
    }

    private function createStudent(string $studentId): StudentAccount
    {
        return StudentAccount::create([
            'student_id' => $studentId,
            'firstname' => 'Security',
            'lastname' => 'Student',
            'email' => strtolower($studentId).'@example.test',
            'password' => 'Strong-Student-Password-123!',
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'A',
            'student_type' => 'Regular',
        ]);
    }
}
