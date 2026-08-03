<?php

namespace Tests\Feature;

use App\Models\Registrar;
use App\Models\StudentAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClearanceFormViewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('clearance_verification_tokens');
        Schema::dropIfExists('office_clearance_status');
        Schema::dropIfExists('clearance_status');
        Schema::dropIfExists('instructor_assignment');
        Schema::dropIfExists('instructor_account');
        Schema::dropIfExists('subject_codes');
        Schema::dropIfExists('student_account');
        Schema::dropIfExists('esignatures');

        Schema::create('student_account', function ($table) {
            $table->string('student_id')->primary();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('program');
            $table->string('year_level');
            $table->string('section');
            $table->string('student_type')->nullable();
            $table->string('semester')->nullable();
            $table->string('middlename')->nullable();
            $table->string('suffix')->nullable();
        });

        Schema::create('clearance_verification_tokens', function ($table) {
            $table->id();
            $table->string('student_id', 50)->unique();
            $table->char('token_hash', 64)->unique();
            $table->text('token_encrypted');
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamp('last_verified_at')->nullable();
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
        });

        Schema::create('instructor_assignment', function ($table) {
            $table->id('assignment_id');
            $table->string('instructor_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('program');
            $table->string('year_level');
            $table->string('section');
        });

        Schema::create('clearance_status', function ($table) {
            $table->id();
            $table->string('student_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('instructor_id');
            $table->string('status')->default('Pending');
            $table->text('remarks')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('office_clearance_status', function ($table) {
            $table->id();
            $table->string('student_id');
            $table->string('office_role');
            $table->string('approver_id')->nullable();
            $table->string('status')->default('Pending');
            $table->text('remarks')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('esignatures', function ($table) {
            $table->id();
            $table->string('signer_id');
            $table->string('signer_role');
            $table->text('signature_data')->nullable();
            $table->string('signer_name')->nullable();
        });
    }

    public function test_student_clearance_form_renders_physical_layout(): void
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
            'student_type' => 'Regular',
            'semester' => '2nd Semester',
        ]);
        DB::table('office_clearance_status')->insert([
            'student_id' => $student->student_id,
            'office_role' => 'registrar',
            'approver_id' => 'REG-1',
            'status' => 'Approved',
        ]);

        $response = $this->actingAs($student, 'student')->get('/student/clearance-form');

        $response->assertOk();
        $response->assertSee('Student Clearance Form');
        $response->assertSee('Offices');
        $response->assertSee('Program Head');
    }

    public function test_registrar_clearance_form_renders_physical_layout_for_selected_student(): void
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
            'student_type' => 'Regular',
            'semester' => '2nd Semester',
        ]);

        $registrar = new Registrar(['id' => 1, 'email' => 'registrar@example.com']);
        $registrar->setAttribute('email', 'registrar@example.com');

        $response = $this->actingAs($registrar, 'registrar')->get('/registrar/clearance-form/'.$student->student_id);

        $response->assertOk();
        $response->assertSee('Student Clearance Form');
        $response->assertSee('Offices');
    }
}
