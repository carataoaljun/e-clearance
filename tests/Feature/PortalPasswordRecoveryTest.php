<?php

namespace Tests\Feature;

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PortalPasswordRecoveryTest extends TestCase
{
    private array $portals = [
        'main-admin' => ['table' => 'main_admin', 'login' => 'login'],
        'instructor' => ['table' => 'instructor_account', 'login' => 'instructor.login'],
        'office' => ['table' => 'admin_personnel', 'login' => 'office.login'],
        'registrar' => ['table' => 'registrar', 'login' => 'registrar.login'],
        'treasurer' => ['table' => 'treasurers', 'login' => 'treasurer.login'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);

        foreach ($this->portals as $portal => $config) {
            Schema::dropIfExists($config['table']);
            Schema::create($config['table'], function ($table) {
                $table->id();
                $table->string('firstname');
                $table->string('lastname');
                $table->string('email')->unique();
                $table->string('password');
            });

            DB::table($config['table'])->insert([
                'firstname' => ucfirst($portal),
                'lastname' => 'Account',
                'email' => "{$portal}@example.com",
                'password' => Hash::make('OldPassword1!'),
            ]);
        }
    }

    public function test_all_portal_logins_contain_the_inline_recovery_flow(): void
    {
        foreach ($this->portals as $config) {
            $this->get(route($config['login']))
                ->assertOk()
                ->assertSee('Verify Account &amp; Send Code', false)
                ->assertSee('Confirm Verification Code')
                ->assertSee('Save New Password');
        }
    }

    public function test_each_portal_sends_a_code_only_for_its_own_registered_email(): void
    {
        Mail::spy();

        foreach ($this->portals as $portal => $config) {
            $sessionKey = 'portal_password_recovery_'.str_replace('-', '_', $portal);

            $this->post(route('portal-password-recovery.send-code', $portal), [
                'email' => "{$portal}@example.com",
            ])->assertRedirect(route($config['login']))
                ->assertSessionHas($sessionKey, function (array $recovery) use ($portal) {
                    return $recovery['email'] === "{$portal}@example.com"
                        && $recovery['stage'] === 'code'
                        && isset($recovery['code_hash'], $recovery['expires_at']);
                });
            session()->forget($sessionKey);

            $this->post(route('portal-password-recovery.send-code', $portal), [
                'email' => 'missing@example.com',
            ])->assertRedirect(route($config['login']))
                ->assertSessionHas('recovery_status')
                ->assertSessionMissing($sessionKey);
        }

        Mail::shouldHaveReceived('send')->times(count($this->portals));
    }

    public function test_each_portal_can_verify_a_code_and_reset_its_password(): void
    {
        foreach ($this->portals as $portal => $config) {
            $sessionKey = 'portal_password_recovery_'.str_replace('-', '_', $portal);

            $this->withSession([
                $sessionKey => [
                    'email' => "{$portal}@example.com",
                    'code_hash' => Hash::make('123456'),
                    'expires_at' => now()->addMinutes(10)->timestamp,
                    'attempts' => 0,
                    'stage' => 'code',
                ],
            ])->post(route('portal-password-recovery.verify-code', $portal), [
                'verification_code' => '123456',
            ])->assertRedirect(route($config['login']))
                ->assertSessionHas("{$sessionKey}.stage", 'reset');

            $this->post(route('portal-password-recovery.reset', $portal), [
                'password' => 'NewPassword1!',
                'password_confirmation' => 'NewPassword1!',
            ])->assertRedirect(route($config['login']))
                ->assertSessionMissing($sessionKey);

            $password = DB::table($config['table'])->where('email', "{$portal}@example.com")->value('password');
            $this->assertTrue(Hash::check('NewPassword1!', $password), "{$portal} password was not updated.");
        }
    }
}
