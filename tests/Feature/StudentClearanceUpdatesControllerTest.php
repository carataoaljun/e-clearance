<?php

namespace Tests\Feature;

use App\Http\Controllers\Student\ClearanceUpdatesController;
use App\Models\StudentAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentClearanceUpdatesControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('office_clearance_status');
        Schema::dropIfExists('clearance_status');
        Schema::dropIfExists('instructor_assignment');
        Schema::dropIfExists('instructor_account');
        Schema::dropIfExists('subject_codes');
        Schema::dropIfExists('student_account');

        Schema::create('student_account', function ($table) {
            $table->id();
            $table->string('student_id')->unique();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('program');
            $table->string('year_level');
            $table->string('section');
            $table->timestamps();
        });

        Schema::create('subject_codes', function ($table) {
            $table->id('subject_id');
            $table->string('subject_code')->unique();
            $table->string('subject_description')->nullable();
            $table->string('year_level');
            $table->string('program');
            $table->string('semester');
        });

        Schema::create('instructor_account', function ($table) {
            $table->id();
            $table->string('instructor_id')->unique();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('department');
            $table->timestamps();
        });

        Schema::create('instructor_assignment', function ($table) {
            $table->id('assignment_id');
            $table->string('instructor_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('program');
            $table->string('year_level');
            $table->string('section');
            $table->timestamps();
        });

        Schema::create('clearance_status', function ($table) {
            $table->id();
            $table->string('student_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('instructor_id');
            $table->string('status')->default('Pending');
            $table->text('remarks')->nullable();
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::create('office_clearance_status', function ($table) {
            $table->id();
            $table->string('student_id');
            $table->string('office_role');
            $table->string('approver_id')->nullable();
            $table->string('status')->default('Pending');
            $table->text('remarks')->nullable();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function test_student_can_request_instructor_clearance_before_any_submission(): void
    {
        $student = StudentAccount::create([
            'student_id' => '2023-0387',
            'firstname' => 'Aljun',
            'lastname' => 'Caratao',
            'email' => 'aljun@example.com',
            'password' => bcrypt('secret'),
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'East',
        ]);

        $subjectId = DB::table('subject_codes')->insertGetId([
            'subject_code' => 'IT101',
            'subject_description' => 'Programming',
            'year_level' => '4',
            'program' => 'BSIT',
            'semester' => '1st',
        ]);

        $instructorId = DB::table('instructor_account')->insertGetId([
            'instructor_id' => '1234',
            'firstname' => 'Aljun',
            'lastname' => 'Instructor',
            'email' => 'instructor@example.com',
            'password' => bcrypt('secret'),
            'department' => 'BSIT',
        ]);

        DB::table('instructor_assignment')->insert([
            'instructor_id' => '1234',
            'subject_id' => $subjectId,
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'East',
        ]);

        $controller = new ClearanceUpdatesController;
        $workflow = $controller->buildWorkflowData($student);

        $this->assertSame('Pending', $workflow['instructorItems'][0]['status']);
        $this->assertTrue($workflow['instructorItems'][0]['can_submit']);
    }

    public function test_it_builds_submission_prerequisites_for_student_workflow(): void
    {
        $student = StudentAccount::create([
            'student_id' => '2023-0387',
            'firstname' => 'Aljun',
            'lastname' => 'Caratao',
            'email' => 'aljun@example.com',
            'password' => bcrypt('secret'),
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'East',
        ]);

        $subjectId = DB::table('subject_codes')->insertGetId([
            'subject_code' => 'IT101',
            'subject_description' => 'Programming',
            'year_level' => '4',
            'program' => 'BSIT',
            'semester' => '1st',
        ]);

        $instructorId = DB::table('instructor_account')->insertGetId([
            'instructor_id' => '1234',
            'firstname' => 'Aljun',
            'lastname' => 'Instructor',
            'email' => 'instructor@example.com',
            'password' => bcrypt('secret'),
            'department' => 'BSIT',
        ]);

        DB::table('instructor_assignment')->insert([
            'instructor_id' => '1234',
            'subject_id' => $subjectId,
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'East',
        ]);

        DB::table('clearance_status')->insert([
            'student_id' => $student->student_id,
            'subject_id' => $subjectId,
            'instructor_id' => '1234',
            'status' => 'Approved',
        ]);

        DB::table('office_clearance_status')->insert([
            'student_id' => $student->student_id,
            'office_role' => 'Section Treasurer',
            'status' => 'Pending',
        ]);

        DB::table('office_clearance_status')->insert([
            'student_id' => $student->student_id,
            'office_role' => 'Department Treasurer',
            'status' => 'Approved',
        ]);

        $controller = new ClearanceUpdatesController;
        $workflow = $controller->buildWorkflowData($student);

        $this->assertSame(1, $workflow['summary']['subjectsApproved']);
        $this->assertFalse($workflow['instructorItems'][0]['can_submit']);
        $this->assertFalse(collect($workflow['officeItems'])->firstWhere('key', 'section treasurer')['can_submit']);
        $this->assertFalse(collect($workflow['officeItems'])->firstWhere('key', 'department treasurer')['can_submit']);
        $this->assertFalse(collect($workflow['officeItems'])->firstWhere('key', 'dean')['can_submit']);
    }

    public function test_student_office_request_uses_normalized_role_key(): void
    {
        $student = StudentAccount::create([
            'student_id' => '2023-0388',
            'firstname' => 'Maria',
            'lastname' => 'Cruz',
            'email' => 'maria@example.com',
            'password' => bcrypt('secret'),
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'East',
        ]);

        Auth::shouldReceive('guard')->with('student')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($student);

        $request = Request::create('/student/clearance/submit-office', 'POST', [
            'office_role' => 'Section Treasurer',
        ]);

        $controller = new ClearanceUpdatesController;
        $controller->submitOffice($request);

        $this->assertDatabaseHas('office_clearance_status', [
            'student_id' => $student->student_id,
            'office_role' => 'section treasurer',
            'status' => 'Pending',
        ]);

    }

    public function test_office_clearance_not_requested_status_shows_for_unsubmitted_steps(): void
    {
        $student = StudentAccount::create([
            'student_id' => '2023-0389',
            'firstname' => 'Rosa',
            'lastname' => 'Santos',
            'email' => 'rosa@example.com',
            'password' => bcrypt('secret'),
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'East',
        ]);

        Auth::shouldReceive('guard')->with('student')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($student);

        $controller = new ClearanceUpdatesController;
        $workflow = $controller->buildWorkflowData($student);

        $sectionTreasurer = collect($workflow['officeItems'])->firstWhere('key', 'section treasurer');

        $this->assertSame('Not Requested', $sectionTreasurer['status']);
        $this->assertTrue($sectionTreasurer['can_submit']);
    }

    public function test_dean_and_registrar_roles_are_normalized_for_student_workflow(): void
    {
        $student = StudentAccount::create([
            'student_id' => '2024-1001',
            'firstname' => 'Juan',
            'lastname' => 'Dela Cruz',
            'email' => 'juan@example.com',
            'password' => bcrypt('secret'),
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'East',
        ]);

        DB::table('office_clearance_status')->insert([
            [
                'student_id' => $student->student_id,
                'office_role' => 'Office of the Dean',
                'status' => 'Pending',
            ],
            [
                'student_id' => $student->student_id,
                'office_role' => 'Registrar Office',
                'status' => 'Approved',
            ],
        ]);

        $controller = new ClearanceUpdatesController;
        $workflow = $controller->buildWorkflowData($student);

        $dean = collect($workflow['officeItems'])->firstWhere('key', 'dean');
        $registrar = collect($workflow['officeItems'])->firstWhere('key', 'registrar');

        $this->assertSame('Pending', $dean['status']);
        $this->assertSame('Approved', $registrar['status']);
    }

    public function test_extra_office_steps_are_available_but_are_not_dean_requirements(): void
    {
        $student = StudentAccount::create([
            'student_id' => '2024-1002',
            'firstname' => 'Anna',
            'lastname' => 'Reyes',
            'email' => 'anna@example.com',
            'password' => bcrypt('secret'),
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'East',
        ]);

        DB::table('office_clearance_status')->insert([
            ['student_id' => $student->student_id, 'office_role' => 'section treasurer', 'status' => 'Approved'],
            ['student_id' => $student->student_id, 'office_role' => 'department treasurer', 'status' => 'Approved'],
            ['student_id' => $student->student_id, 'office_role' => 'property custodian', 'status' => 'Approved'],
            ['student_id' => $student->student_id, 'office_role' => 'scc adviser', 'status' => 'Approved'],
            ['student_id' => $student->student_id, 'office_role' => 'sas director', 'status' => 'Approved'],
            ['student_id' => $student->student_id, 'office_role' => 'guidance office', 'status' => 'Approved'],
            ['student_id' => $student->student_id, 'office_role' => 'library', 'status' => 'Approved'],
        ]);

        $controller = new ClearanceUpdatesController;
        $workflow = $controller->buildWorkflowData($student);

        $guidance = collect($workflow['officeItems'])->firstWhere('key', 'guidance office');
        $library = collect($workflow['officeItems'])->firstWhere('key', 'library');
        $dean = collect($workflow['officeItems'])->firstWhere('key', 'dean');

        $this->assertSame('Approved', $guidance['status']);
        $this->assertSame('Approved', $library['status']);
        $this->assertSame(['section treasurer', 'department treasurer'], $dean['requires']);
        $this->assertFalse($dean['can_submit']);
    }

    public function test_dean_requires_both_treasurers_and_all_subject_clearances(): void
    {
        $student = StudentAccount::create([
            'student_id' => '2024-1003',
            'firstname' => 'Lea',
            'lastname' => 'Santos',
            'email' => 'lea@example.com',
            'password' => bcrypt('secret'),
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'East',
        ]);

        $subjectId = DB::table('subject_codes')->insertGetId([
            'subject_code' => 'IT401',
            'subject_description' => 'Capstone Project',
            'year_level' => '4',
            'program' => 'BSIT',
            'semester' => '1st',
        ]);

        DB::table('instructor_account')->insert([
            'instructor_id' => '5678',
            'firstname' => 'Maria',
            'lastname' => 'Reyes',
            'email' => 'maria.reyes@example.com',
            'password' => bcrypt('secret'),
            'department' => 'BSIT',
        ]);

        DB::table('instructor_assignment')->insert([
            'instructor_id' => '5678',
            'subject_id' => $subjectId,
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'East',
        ]);

        DB::table('office_clearance_status')->insert([
            'student_id' => $student->student_id,
            'office_role' => 'section treasurer',
            'status' => 'Approved',
        ]);

        $controller = new ClearanceUpdatesController;
        $dean = collect($controller->buildWorkflowData($student)['officeItems'])->firstWhere('key', 'dean');

        $this->assertFalse($dean['can_submit']);

        DB::table('office_clearance_status')->insert([
            'student_id' => $student->student_id,
            'office_role' => 'department treasurer',
            'status' => 'Approved',
        ]);

        $dean = collect($controller->buildWorkflowData($student)['officeItems'])->firstWhere('key', 'dean');
        $this->assertFalse($dean['can_submit']);

        DB::table('clearance_status')->insert([
            'student_id' => $student->student_id,
            'subject_id' => $subjectId,
            'instructor_id' => '5678',
            'status' => 'Approved',
        ]);

        $dean = collect($controller->buildWorkflowData($student)['officeItems'])->firstWhere('key', 'dean');

        $this->assertSame(['section treasurer', 'department treasurer'], $dean['requires']);
        $this->assertTrue($dean['can_submit']);
    }
}
