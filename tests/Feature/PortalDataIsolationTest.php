<?php

namespace Tests\Feature;

use App\Models\AdminPersonnel;
use App\Models\Registrar;
use App\Models\StudentAccount;
use App\Models\Treasurer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PortalDataIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_pages_only_show_records_assigned_to_the_authenticated_office(): void
    {
        $libraryStudent = $this->student('OFFICE-LIB-001', 'LibraryVisible', 'BSIT', 'A');
        $guidanceStudent = $this->student('OFFICE-GUI-001', 'GuidanceHidden', 'BSED', 'B');
        $library = $this->office('LIB-ISO-001', 'library', 'Library');

        $this->officeClearance($libraryStudent, 'library');
        $this->officeClearance($guidanceStudent, 'guidance office');
        $librarySubmission = $this->officeSubmission($libraryStudent, 'library');
        $guidanceSubmission = $this->officeSubmission($guidanceStudent, 'guidance office');

        $this->actingAs($library, 'office');
        $this->getPage(route('office.dashboard'))
            ->assertOk()
            ->assertSee('LibraryVisible')
            ->assertDontSee('GuidanceHidden');

        $this->actingAs($library, 'office');
        $this->getPage(route('office.clearance.requests'))
            ->assertOk()
            ->assertSee('LibraryVisible')
            ->assertDontSee('GuidanceHidden')
            ->assertSee('Library Clearance Requests');

        $this->actingAs($library, 'office');
        $this->getPage(route('office.submissions'))
            ->assertOk()
            ->assertSee('library-proof.pdf')
            ->assertDontSee('guidance-office-proof.pdf');

        $this->actingAs($library, 'office');
        $this->getPage(route('office.submissions.file', $guidanceSubmission))
            ->assertNotFound();

        $this->actingAs($library, 'office')
            ->post(route('office.clearance.status'), [
                'student_id' => $guidanceStudent->student_id,
                'status' => 'Pending',
            ])->assertForbidden();

        $this->assertDatabaseHas('office_submissions', ['id' => $librarySubmission, 'office' => 'library']);
        $this->assertDatabaseHas('office_clearance_status', [
            'student_id' => $guidanceStudent->student_id,
            'office_role' => 'guidance office',
            'status' => 'Pending',
        ]);
    }

    public function test_program_head_only_sees_students_in_their_assigned_program(): void
    {
        $bsitStudent = $this->student('DEAN-BSIT-001', 'BsitVisible', 'BSIT', 'A');
        $bsedStudent = $this->student('DEAN-BSED-001', 'BsedHidden', 'BSED', 'A');
        $programHead = $this->office('HEAD-BSIT-001', 'program_head_bsit', 'BSIT Program Head');

        $this->officeClearance($bsitStudent, 'dean');
        $this->officeClearance($bsedStudent, 'dean');

        $this->actingAs($programHead, 'office');
        $this->getPage(route('office.clearance.requests'))
            ->assertOk()
            ->assertSee('BsitVisible')
            ->assertDontSee('BsedHidden');

        $this->actingAs($programHead, 'office')
            ->post(route('office.clearance.status'), [
                'student_id' => $bsedStudent->student_id,
                'status' => 'Pending',
            ])->assertForbidden();
    }

    public function test_registrar_dashboard_counts_only_registrar_requests(): void
    {
        $pending = $this->student('REG-PENDING-001', 'RegistrarPending', 'BSIT', 'A');
        $approved = $this->student('REG-APPROVED-001', 'RegistrarApproved', 'BSED', 'B');
        $otherOffice = $this->student('REG-OTHER-001', 'OtherOfficeOnly', 'BSHM', 'C');
        $registrar = Registrar::create([
            'registrar_id' => 'REG-ISO-001',
            'firstname' => 'Scoped',
            'lastname' => 'Registrar',
            'email' => 'scoped-registrar@example.test',
            'password' => 'Strong-Password-123!',
            'role' => 'registrar',
        ]);

        $this->officeClearance($pending, 'registrar', 'Pending');
        $this->officeClearance($approved, 'registrar', 'Approved');
        $this->officeClearance($otherOffice, 'library', 'Approved');

        $this->actingAs($registrar, 'registrar');
        $this->getPage(route('registrar.dashboard'))
            ->assertOk()
            ->assertViewHas('totalStudents', 2)
            ->assertViewHas('pendingRequests', 1)
            ->assertViewHas('clearedRequests', 1)
            ->assertViewHas('studentsByProgram', function ($programs): bool {
                return $programs->pluck('program')->sort()->values()->all() === ['BSED', 'BSIT'];
            })
            ->assertSee('Assigned Students')
            ->assertDontSee('Overall status of clearance records across the college.');

        $this->actingAs($registrar, 'registrar');
        $this->getPage(route('registrar.clearance.form', $otherOffice->student_id))
            ->assertForbidden();
    }

    public function test_section_treasurer_pages_and_actions_are_limited_to_the_assigned_section(): void
    {
        $assigned = $this->student('TREASURY-A-001', 'SectionVisible', 'BSIT', 'A');
        $otherSection = $this->student('TREASURY-B-001', 'SectionHidden', 'BSIT', 'B');
        $treasurer = Treasurer::create([
            'treasurer_id' => 'TREAS-ISO-001',
            'firstname' => 'Section',
            'lastname' => 'Treasurer',
            'email' => 'section-treasurer@example.test',
            'password' => 'Strong-Password-123!',
            'treasurer_type' => 'section',
            'program' => 'BSIT',
            'year_level' => '1',
            'section' => 'A',
        ]);

        $this->officeClearance($assigned, 'section treasurer');
        $this->officeClearance($otherSection, 'section treasurer');

        $this->actingAs($treasurer, 'treasurer');
        $this->getPage(route('treasurer.dashboard'))
            ->assertOk()
            ->assertSee('SectionVisible')
            ->assertDontSee('SectionHidden');

        $this->actingAs($treasurer, 'treasurer')
            ->post(route('treasurer.clearance.status'), [
                'student_id' => $otherSection->student_id,
                'status' => 'Pending',
            ])->assertForbidden();
    }

    private function student(string $studentId, string $firstname, string $program, string $section): StudentAccount
    {
        return StudentAccount::create([
            'student_id' => $studentId,
            'firstname' => $firstname,
            'lastname' => 'Isolation',
            'email' => strtolower($studentId).'@example.test',
            'password' => 'Strong-Password-123!',
            'program' => $program,
            'year_level' => '1',
            'section' => $section,
            'student_type' => 'Regular',
        ]);
    }

    private function office(string $personnelId, string $role, string $office): AdminPersonnel
    {
        return AdminPersonnel::create([
            'personnel_id' => $personnelId,
            'firstname' => 'Scoped',
            'lastname' => 'Officer',
            'email' => strtolower($personnelId).'@example.test',
            'password' => 'Strong-Password-123!',
            'office' => $office,
            'role' => $role,
        ]);
    }

    private function officeClearance(StudentAccount $student, string $role, string $status = 'Pending'): void
    {
        DB::table('office_clearance_status')->insert([
            'student_id' => $student->student_id,
            'office_role' => $role,
            'approver_id' => $student->student_id,
            'status' => $status,
            'updated_at' => now(),
        ]);
    }

    private function officeSubmission(StudentAccount $student, string $office): int
    {
        return DB::table('office_submissions')->insertGetId([
            'student_id' => $student->student_id,
            'personnel_id' => $office,
            'office' => $office,
            'approver_role' => $office,
            'file_path' => "office_submissions/{$student->student_id}/{$office}/proof.pdf",
            'file_name' => str_replace(' ', '-', $office).'-proof.pdf',
            'file_type' => 'application/pdf',
            'status' => 'Pending',
            'submitted_at' => now(),
        ]);
    }

    private function getPage(string $uri): TestResponse
    {
        $bufferLevel = ob_get_level();

        try {
            return $this->get($uri);
        } finally {
            // Blade's nested portal layout can leave an empty capture buffer in
            // the test process even though the HTTP response is fully rendered.
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
        }
    }
}
