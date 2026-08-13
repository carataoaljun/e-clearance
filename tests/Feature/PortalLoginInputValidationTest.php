<?php

namespace Tests\Feature;

use App\Support\StrongPassword;
use Tests\TestCase;

class PortalLoginInputValidationTest extends TestCase
{
    /** @return string[] */
    private function loginPages(): array
    {
        return ['login', 'student.login', 'instructor.login', 'office.login', 'registrar.login', 'treasurer.login'];
    }

    /** @return array<int, array{0: string, 1: array<string, string>}> Login submissions minus the password. */
    private function loginSubmissions(): array
    {
        return [
            ['login.post', ['email' => 'admin@example.test']],
            ['student.login.submit', ['student_id' => '2026-0001']],
            ['instructor.login.submit', ['email' => 'instructor@example.test']],
            ['office.login.submit', ['login' => 'OFF-001', 'role' => 'library']],
            ['registrar.login.submit', ['login' => 'REG-001']],
            ['treasurer.login.submit', ['login' => 'TRE-001', 'treasurer_type' => 'department']],
        ];
    }

    public function test_every_portal_login_loads_the_shared_inline_validation(): void
    {
        $loginRoutes = $this->loginPages();

        foreach ($loginRoutes as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee('css/auth-form-validation.css', false)
                ->assertSee('js/auth-form-validation.js', false)
                ->assertSee('data-validation-label=', false)
                ->assertSee('maxlength="128"', false)
                ->assertSee('data-password-primary', false)
                ->assertSee('data-password-confirmation', false)
                ->assertSee('href="'.route('landing').'"', false)
                ->assertSee('Back to Landing Page');
        }
    }

    public function test_login_password_length_is_limited_consistently_on_the_server(): void
    {
        $longPassword = str_repeat('Aa1!', 33); // 132 characters, complex enough to reach the length rule.

        foreach ($this->loginSubmissions() as [$route, $payload]) {
            $this->post(route($route), $payload + ['password' => $longPassword])
                ->assertRedirect()
                ->assertSessionHasErrors('password');
        }
    }

    public function test_every_login_password_field_advertises_the_complexity_rule(): void
    {
        foreach ($this->loginPages() as $route) {
            $html = $this->get(route($route))->assertOk()->getContent();

            $this->assertSame(1, preg_match('/<input[^>]*autocomplete="current-password"[^>]*>/', $html, $matches), "No password field on {$route}.");
            $this->assertStringContainsString('minlength="8"', $matches[0], "Missing minimum length on {$route}.");
            $this->assertStringContainsString('pattern="'.StrongPassword::PATTERN.'"', $matches[0], "Missing complexity pattern on {$route}.");
            $this->assertStringContainsString('data-validation-rule="strong-password"', $matches[0], "Missing inline rule on {$route}.");
        }
    }

    public function test_login_rejects_passwords_that_miss_a_required_character_class(): void
    {
        $weakPasswords = [
            'Sh0rt!a',    // fewer than eight characters
            'password1!', // no uppercase letter
            'PASSWORD1!', // no lowercase letter
            'Password!!', // no number
            'Password11', // no special character
        ];

        foreach ($this->loginSubmissions() as [$route, $payload]) {
            foreach ($weakPasswords as $password) {
                $this->from(route($route))
                    ->post(route($route), $payload + ['password' => $password])
                    ->assertRedirect()
                    ->assertSessionHasErrors(['password' => StrongPassword::REQUIREMENT_MESSAGE]);
            }
        }
    }

    public function test_student_android_app_hides_the_back_to_landing_button(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 Android MCCStudentAndroid/1.2.0-debug')
            ->get(route('student.login'))
            ->assertOk()
            ->assertDontSee('href="'.route('landing').'"', false)
            ->assertDontSee('Back to Landing Page');
    }
}
