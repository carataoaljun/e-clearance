<?php

namespace Tests\Feature;

use App\Http\Controllers\Treasurer\DashboardController;
use App\Models\StudentAccount;
use App\Models\Treasurer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasurerDashboardControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('office_clearance_status');
        Schema::dropIfExists('office_submissions');
        Schema::dropIfExists('student_account');
        Schema::dropIfExists('treasurers');

        Schema::create('treasurers', function ($table) {
            $table->id();
            $table->string('treasurer_id', 50)->unique();
            $table->string('firstname', 100);
            $table->string('middlename', 100)->nullable();
            $table->string('lastname', 100);
            $table->string('suffix', 10)->nullable();
            $table->string('email', 100)->unique();
            $table->string('password', 255);
            $table->string('treasurer_type', 20);
            $table->string('department', 100)->nullable();
            $table->string('program', 100)->nullable();
            $table->tinyInteger('year_level')->nullable();
            $table->string('section', 50)->nullable();
            $table->timestamps();
        });

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

        Schema::create('office_clearance_status', function ($table) {
            $table->id();
            $table->string('student_id');
            $table->string('office_role');
            $table->string('approver_id')->nullable();
            $table->string('status')->default('Pending');
            $table->text('remarks')->nullable();
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::create('office_submissions', function ($table) {
            $table->id();
            $table->string('student_id');
            $table->string('office');
            $table->string('file_name');
            $table->string('status')->default('Pending');
            $table->timestamp('submitted_at')->useCurrent();
        });
    }

    public function test_section_treasurer_only_sees_section_treasurer_clearance_rows(): void
    {
        $treasurer = Treasurer::create([
            'treasurer_id' => 'TR-1001',
            'firstname' => 'Section',
            'middlename' => null,
            'lastname' => 'Treasurer',
            'suffix' => null,
            'email' => 'section@example.com',
            'password' => bcrypt('secret'),
            'treasurer_type' => 'section',
            'program' => 'BSIT',
            'year_level' => 4,
            'section' => 'East',
        ]);

        $studentSame = StudentAccount::create([
            'student_id' => '2024-0001',
            'firstname' => 'Alice',
            'lastname' => 'Smith',
            'email' => 'alice@example.com',
            'password' => bcrypt('secret'),
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'East',
        ]);

        $studentOther = StudentAccount::create([
            'student_id' => '2024-0002',
            'firstname' => 'Bob',
            'lastname' => 'Jones',
            'email' => 'bob@example.com',
            'password' => bcrypt('secret'),
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'West',
        ]);

        DB::table('office_clearance_status')->insert([
            [
                'student_id' => $studentSame->student_id,
                'office_role' => 'Section Treasurer',
                'status' => 'Pending',
            ],
            [
                'student_id' => $studentOther->student_id,
                'office_role' => 'Section Treasurer',
                'status' => 'Pending',
            ],
            [
                'student_id' => $studentSame->student_id,
                'office_role' => 'Department Treasurer',
                'status' => 'Pending',
            ],
        ]);

        Auth::shouldReceive('guard')->with('treasurer')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($treasurer);

        $controller = new DashboardController;
        $response = $controller->clearanceUpdates();
        $data = $response->getData();
        $officeClearances = $data['officeClearances'];

        $this->assertCount(1, $officeClearances);
        $this->assertSame('Section Treasurer', $officeClearances[0]->office_role);
        $this->assertSame($studentSame->student_id, $officeClearances[0]->student_id);
    }

    public function test_department_treasurer_sees_department_clearance_rows(): void
    {
        $treasurer = Treasurer::create([
            'treasurer_id' => 'TR-2001',
            'firstname' => 'Department',
            'middlename' => null,
            'lastname' => 'Treasurer',
            'suffix' => null,
            'email' => 'department@example.com',
            'password' => bcrypt('secret'),
            'treasurer_type' => 'department',
            'department' => 'BSIT',
            'program' => null,
            'year_level' => null,
            'section' => null,
        ]);

        $studentSame = StudentAccount::create([
            'student_id' => '2024-0003',
            'firstname' => 'Cathy',
            'lastname' => 'Rivera',
            'email' => 'cathy@example.com',
            'password' => bcrypt('secret'),
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'North',
        ]);

        $studentOther = StudentAccount::create([
            'student_id' => '2024-0004',
            'firstname' => 'Derek',
            'lastname' => 'Lopez',
            'email' => 'derek@example.com',
            'password' => bcrypt('secret'),
            'program' => 'BSED',
            'year_level' => '4',
            'section' => 'East',
        ]);

        DB::table('office_clearance_status')->insert([
            [
                'student_id' => $studentSame->student_id,
                'office_role' => 'Department Treasurer',
                'status' => 'Pending',
            ],
            [
                'student_id' => $studentOther->student_id,
                'office_role' => 'Department Treasurer',
                'status' => 'Pending',
            ],
        ]);

        Auth::shouldReceive('guard')->with('treasurer')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($treasurer);

        $controller = new DashboardController;
        $response = $controller->clearanceUpdates();
        $data = $response->getData();
        $officeClearances = $data['officeClearances'];

        $this->assertCount(1, $officeClearances);
        $this->assertSame('Department Treasurer', $officeClearances[0]->office_role);
        $this->assertSame($studentSame->student_id, $officeClearances[0]->student_id);
    }
}
