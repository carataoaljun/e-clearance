<?php

namespace Tests\Feature;

use App\Models\MainAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MainAdminSchemaCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_admin_can_change_password_without_an_updated_at_column(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')
            ->withSession(['admin_id' => $admin->id])
            ->put(route('password.update'), [
                'current_password' => 'Current-Admin-Password-123!',
                'password' => 'New-Admin-Password-456!',
                'password_confirmation' => 'New-Admin-Password-456!',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check(
            'New-Admin-Password-456!',
            (string) $admin->fresh()->password,
        ));
    }

    public function test_main_admin_remembered_login_does_not_require_a_remember_token_column(): void
    {
        $admin = $this->createAdmin();

        $this->loginThroughDeviceCode('admin', 'login.post', [
            'email' => $admin->email,
            'password' => 'Current-Admin-Password-123!',
            'remember' => true,
        ], 'login.otp.verify', '715204')->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    private function createAdmin(): MainAdmin
    {
        return MainAdmin::create([
            'name' => 'Schema Test Administrator',
            'email' => 'schema-admin@example.test',
            'password' => 'Current-Admin-Password-123!',
        ]);
    }
}
