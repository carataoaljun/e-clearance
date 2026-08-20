<?php

namespace Tests\Feature;

use App\Models\StudentAccount;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentPasswordRecoveryTest extends TestCase
{
    private StudentAccount $student;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('student_account');
        Schema::create('student_account', function ($table) {
            $table->id();
            $table->string('student_id')->unique();
            $table->string('firstname');
            $table->string('middlename')->nullable();
            $table->string('lastname');
            $table->string('suffix')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('program');
            $table->string('year_level');
            $table->string('section');
            $table->string('student_type')->default('Regular');
            $table->timestamp('created_at')->nullable();
        });

        $this->student = StudentAccount::create([
            'student_id' => '2026-0001',
            'firstname' => 'Test',
            'lastname' => 'Student',
            'email' => 'student@example.com',
            'password' => Hash::make('OldPassword1!'),
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'A',
            'student_type' => 'Regular',
        ]);
    }

    public function test_student_login_contains_the_inline_recovery_steps(): void
    {
        $this->get(route('student.login'))
            ->assertOk()
            ->assertSee('login-panel', false)
            ->assertSee('email-panel', false)
            ->assertSee('code-panel', false)
            ->assertSee('reset-panel', false);
    }

    public function test_recovery_code_is_only_prepared_for_an_existing_student_email(): void
    {
        Mail::spy();

        $this->post(route('student.password-recovery.send-code'), [
            'email' => 'missing@example.com',
        ])->assertRedirect(route('student.login'))
            ->assertSessionHas('recovery_status')
            ->assertSessionMissing('student_password_recovery');
        Mail::shouldNotHaveReceived('send');

        $this->post(route('student.password-recovery.send-code'), [
            'email' => strtoupper($this->student->email),
        ])->assertRedirect(route('student.login'))
            ->assertSessionHas('student_password_recovery', function (array $recovery) {
                return $recovery['email'] === $this->student->email
                    && $recovery['stage'] === 'code'
                    && isset($recovery['code_hash'], $recovery['expires_at']);
            });
        Mail::shouldHaveReceived('send')->once();
    }

    public function test_verified_code_allows_the_student_to_choose_and_use_a_new_password(): void
    {
        $this->withSession([
            'student_password_recovery' => [
                'email' => $this->student->email,
                'code_hash' => Hash::make('123456'),
                'expires_at' => now()->addMinutes(10)->timestamp,
                'attempts' => 0,
                'stage' => 'code',
            ],
        ])->post(route('student.password-recovery.verify-code'), [
            'verification_code' => '123456',
        ])->assertRedirect(route('student.login'))
            ->assertSessionHas('student_password_recovery.stage', 'reset');

        $this->post(route('student.password-recovery.reset'), [
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertRedirect(route('student.login'))
            ->assertSessionMissing('student_password_recovery');

        $this->assertTrue(Hash::check('NewPassword1!', $this->student->fresh()->password));

        $this->loginThroughDeviceCode('student', 'student.login.submit', [
            'student_id' => $this->student->student_id,
            'password' => 'NewPassword1!',
        ], 'student.login.otp.verify')->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($this->student->fresh(), 'student');
    }
}
