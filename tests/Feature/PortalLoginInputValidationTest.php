<?php

namespace Tests\Feature;

use Tests\TestCase;

class PortalLoginInputValidationTest extends TestCase
{
    public function test_every_portal_login_loads_the_shared_inline_validation(): void
    {
        $loginRoutes = [
            'login',
            'student.login',
            'instructor.login',
            'office.login',
            'registrar.login',
            'treasurer.login',
        ];

        foreach ($loginRoutes as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee('css/auth-form-validation.css', false)
                ->assertSee('js/auth-form-validation.js', false)
                ->assertSee('data-validation-label=', false)
                ->assertSee('maxlength="128"', false)
                ->assertSee('data-password-primary', false)
                ->assertSee('data-password-confirmation', false);
        }
    }

    public function test_login_password_length_is_limited_consistently_on_the_server(): void
    {
        $longPassword = str_repeat('a', 129);
        $loginRequests = [
            ['login.post', ['email' => 'admin@example.test', 'password' => $longPassword]],
            ['student.login.submit', ['student_id' => '2026-0001', 'password' => $longPassword]],
            ['instructor.login.submit', ['email' => 'instructor@example.test', 'password' => $longPassword]],
            ['office.login.submit', ['login' => 'OFF-001', 'password' => $longPassword, 'role' => 'library']],
            ['registrar.login.submit', ['login' => 'REG-001', 'password' => $longPassword]],
            ['treasurer.login.submit', ['login' => 'TRE-001', 'password' => $longPassword, 'treasurer_type' => 'department']],
        ];

        foreach ($loginRequests as [$route, $payload]) {
            $this->post(route($route), $payload)
                ->assertRedirect()
                ->assertSessionHasErrors('password');
        }
    }
}

