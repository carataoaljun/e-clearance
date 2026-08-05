<?php

namespace Tests\Feature;

use App\Models\AdminPersonnel;
use App\Models\Instructor;
use App\Models\MainAdmin;
use App\Models\Registrar;
use App\Models\Student;
use App\Models\Treasurer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MainAdminAccountPasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_admin_account_passwords_have_accessible_visibility_toggles(): void
    {
        $layout = file_get_contents(resource_path('views/mainAdmin/layouts/admin.blade.php'));
        $stylesheet = file_get_contents(public_path('css/main_admin_portal.css'));

        $this->assertStringContainsString('function initializePasswordVisibilityToggle(field)', $layout);
        $this->assertStringContainsString('aria-label', $layout);
        $this->assertStringContainsString('bi bi-eye', $layout);
        $this->assertStringContainsString('bi bi-eye-slash', $layout);
        $this->assertStringContainsString('.password-visibility-toggle', $stylesheet);
    }

    public function test_add_and_edit_account_forms_include_password_confirmation_fields(): void
    {
        $admin = $this->createAdmin();

        foreach (['students.index', 'instructors.index', 'personnel.index', 'registrar.index', 'treasurers.index'] as $route) {
            $response = $this->actingAs($admin, 'admin')->get(route($route));

            $response->assertOk();
            $this->assertSame(
                2,
                substr_count($response->getContent(), '<input type="password" name="password_confirmation"'),
                "The [{$route}] page must include confirmation fields in both account forms."
            );
        }
    }

    public function test_add_account_requests_reject_mismatched_password_confirmation(): void
    {
        $this->actingAs($this->createAdmin(), 'admin');

        $cases = [
            ['students.store', 'student_account', [
                'student_id' => '2026-0001',
                'firstname' => 'Test',
                'lastname' => 'Student',
                'email' => 'student-confirmation@example.test',
                'program' => 'BSIT',
                'year_level' => '1',
                'section' => 'A',
                'student_type' => 'Regular',
            ]],
            ['instructors.store', 'instructor_account', [
                'instructor_id' => '1001',
                'firstname' => 'Test',
                'lastname' => 'Instructor',
                'email' => 'instructor-confirmation@example.test',
                'department' => 'BSIT',
            ]],
            ['personnel.store', 'admin_personnel', [
                'firstname' => 'Test',
                'lastname' => 'Personnel',
                'email' => 'personnel-confirmation@example.test',
                'office' => 'Guidance',
                'role' => 'guidance',
            ]],
            ['registrar.store', 'registrar', [
                'firstname' => 'Test',
                'lastname' => 'Registrar',
                'email' => 'registrar-confirmation@example.test',
            ]],
            ['treasurers.store', 'treasurers', [
                'firstname' => 'Test',
                'lastname' => 'Treasurer',
                'email' => 'treasurer-confirmation@example.test',
                'treasurer_type' => 'department',
                'department' => 'BSIT',
            ]],
        ];

        foreach ($cases as [$route, $table, $payload]) {
            $response = $this->from(route($route))->post(route($route), [
                ...$payload,
                'password' => 'StrongPassword1!',
                'password_confirmation' => 'DifferentPassword1!',
            ]);

            $response->assertSessionHasErrors('password');
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_edit_account_requests_reject_mismatched_password_confirmation(): void
    {
        $this->actingAs($this->createAdmin(), 'admin');

        $student = Student::create([
            'student_id' => '2026-0002',
            'firstname' => 'Existing',
            'lastname' => 'Student',
            'email' => 'existing-student@example.test',
            'password' => 'ExistingPassword1!',
            'program' => 'BSIT',
            'year_level' => '1',
            'section' => 'A',
            'student_type' => 'Regular',
        ]);
        $instructor = Instructor::create([
            'instructor_id' => '1002',
            'firstname' => 'Existing',
            'lastname' => 'Instructor',
            'email' => 'existing-instructor@example.test',
            'password' => 'ExistingPassword1!',
            'department' => 'BSIT',
        ]);
        $personnel = AdminPersonnel::create([
            'personnel_id' => 'AP-10002',
            'firstname' => 'Existing',
            'lastname' => 'Personnel',
            'email' => 'existing-personnel@example.test',
            'password' => 'ExistingPassword1!',
            'office' => 'Guidance',
            'role' => 'guidance',
        ]);
        $registrar = Registrar::create([
            'registrar_id' => 'REG-10002',
            'firstname' => 'Existing',
            'lastname' => 'Registrar',
            'email' => 'existing-registrar@example.test',
            'password' => 'ExistingPassword1!',
            'role' => 'registrar',
        ]);
        $treasurer = Treasurer::create([
            'treasurer_id' => 'TR-10002',
            'firstname' => 'Existing',
            'lastname' => 'Treasurer',
            'email' => 'existing-treasurer@example.test',
            'password' => 'ExistingPassword1!',
            'treasurer_type' => 'department',
            'department' => 'BSIT',
        ]);

        $cases = [
            ['students.update', $student->student_id, $student, [
                'firstname' => $student->firstname,
                'lastname' => $student->lastname,
                'email' => $student->email,
                'program' => $student->program,
                'year_level' => $student->year_level,
                'section' => $student->section,
                'student_type' => $student->student_type,
            ]],
            ['instructors.update', $instructor->instructor_id, $instructor, [
                'firstname' => $instructor->firstname,
                'lastname' => $instructor->lastname,
                'email' => $instructor->email,
                'department' => $instructor->department,
            ]],
            ['personnel.update', $personnel->id, $personnel, [
                'firstname' => $personnel->firstname,
                'lastname' => $personnel->lastname,
                'email' => $personnel->email,
                'office' => $personnel->office,
                'role' => $personnel->role,
            ]],
            ['registrar.update', $registrar->id, $registrar, [
                'firstname' => $registrar->firstname,
                'lastname' => $registrar->lastname,
                'email' => $registrar->email,
            ]],
            ['treasurers.update', $treasurer->id, $treasurer, [
                'firstname' => $treasurer->firstname,
                'lastname' => $treasurer->lastname,
                'email' => $treasurer->email,
                'treasurer_type' => $treasurer->treasurer_type,
                'department' => $treasurer->department,
            ]],
        ];

        foreach ($cases as [$route, $parameter, $account, $payload]) {
            $passwordBeforeRequest = $account->password;

            $response = $this->put(route($route, $parameter), [
                ...$payload,
                'password' => 'ReplacementPassword1!',
                'password_confirmation' => 'DifferentPassword1!',
            ]);

            $response->assertSessionHasErrors('password');
            $this->assertSame($passwordBeforeRequest, $account->fresh()->password);
        }
    }

    public function test_add_account_can_still_auto_generate_a_password_when_both_password_fields_are_blank(): void
    {
        $this->actingAs($this->createAdmin(), 'admin')
            ->post(route('registrar.store'), [
                'firstname' => 'Auto',
                'lastname' => 'Generated',
                'email' => 'auto-generated-password@example.test',
            ])
            ->assertRedirect(route('registrar.index'))
            ->assertSessionHasNoErrors();

        $registrar = Registrar::where('email', 'auto-generated-password@example.test')->sole();

        $this->assertNotEmpty($registrar->password);
        $this->assertTrue(Hash::needsRehash($registrar->password) === false);
    }

    private function createAdmin(): MainAdmin
    {
        return MainAdmin::create([
            'email' => 'admin-password-confirmation@example.test',
            'password' => 'AdminPassword1!',
        ]);
    }
}
