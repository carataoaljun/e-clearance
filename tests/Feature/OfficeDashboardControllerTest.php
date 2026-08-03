<?php

namespace Tests\Feature;

use App\Http\Controllers\Office\DashboardController;
use App\Models\AdminPersonnel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OfficeDashboardControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('office_clearance_status');
        Schema::dropIfExists('student_account');
        Schema::dropIfExists('admin_personnel');

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

        Schema::create('admin_personnel', function ($table) {
            $table->id();
            $table->string('personnel_id')->unique();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('office')->nullable();
            $table->string('role');
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
    }

    public function test_program_head_office_can_see_dean_clearance_requests(): void
    {
        $student = DB::table('student_account')->insertGetId([
            'student_id' => '2026-0001',
            'firstname' => 'Claire',
            'lastname' => 'Reyes',
            'email' => 'claire@example.com',
            'password' => bcrypt('secret'),
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'North',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $personnel = AdminPersonnel::create([
            'personnel_id' => 'AP-10001',
            'firstname' => 'Dino',
            'lastname' => 'Illustrisimo',
            'email' => 'dino@example.com',
            'password' => bcrypt('secret'),
            'office' => '',
            'role' => 'program_head_bsit',
        ]);

        DB::table('office_clearance_status')->insert([
            'student_id' => '2026-0001',
            'office_role' => 'dean',
            'status' => 'Pending',
        ]);

        Auth::shouldReceive('guard')->with('office')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($personnel);

        $controller = new DashboardController;
        $response = $controller->clearanceRequests();
        $data = $response->getData();

        $this->assertCount(1, $data['requests']);
        $this->assertSame('dean', $data['requests'][0]->office_role);
    }

    public function test_registrar_office_can_see_registrar_clearance_requests(): void
    {
        $student = DB::table('student_account')->insertGetId([
            'student_id' => '2026-0002',
            'firstname' => 'Jose',
            'lastname' => 'Santos',
            'email' => 'jose@example.com',
            'password' => bcrypt('secret'),
            'program' => 'BSIT',
            'year_level' => '3',
            'section' => 'East',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $personnel = AdminPersonnel::create([
            'personnel_id' => 'AP-10002',
            'firstname' => 'Regina',
            'lastname' => 'Torres',
            'email' => 'regina@example.com',
            'password' => bcrypt('secret'),
            'office' => 'Registrar Office',
            'role' => 'registrar',
        ]);

        DB::table('office_clearance_status')->insert([
            'student_id' => '2026-0002',
            'office_role' => 'registrar',
            'status' => 'Approved',
        ]);

        Auth::shouldReceive('guard')->with('office')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($personnel);

        $controller = new DashboardController;
        $response = $controller->clearanceRequests();
        $data = $response->getData();

        $this->assertCount(1, $data['requests']);
        $this->assertSame('registrar', $data['requests'][0]->office_role);
    }

    public function test_each_special_office_account_can_see_only_their_clearance_requests(): void
    {
        $student = DB::table('student_account')->insertGetId([
            'student_id' => '2026-0003',
            'firstname' => 'Marian',
            'lastname' => 'Lopez',
            'email' => 'marian@example.com',
            'password' => bcrypt('secret'),
            'program' => 'BSIT',
            'year_level' => '3',
            'section' => 'West',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $officeRoles = [
            'property_custodian' => 'property custodian',
            'scc_adviser' => 'scc adviser',
            'sas_director' => 'sas director',
            'guidance' => 'guidance office',
            'library' => 'library',
        ];

        foreach ($officeRoles as $role => $officeRole) {
            DB::table('office_clearance_status')->insert([
                'student_id' => '2026-0003',
                'office_role' => $officeRole,
                'status' => 'Pending',
            ]);

            $personnel = AdminPersonnel::create([
                'personnel_id' => 'AP-1000'.$role,
                'firstname' => ucfirst(str_replace('_', ' ', $role)),
                'lastname' => 'User',
                'email' => $role.'@example.com',
                'password' => bcrypt('secret'),
                'office' => '',
                'role' => $role,
            ]);

            Auth::shouldReceive('guard')->with('office')->once()->andReturnSelf();
            Auth::shouldReceive('user')->once()->andReturn($personnel);

            $controller = new DashboardController;
            $response = $controller->clearanceRequests();
            $data = $response->getData();

            $this->assertCount(1, $data['requests']);
            $this->assertSame($officeRole, $data['requests'][0]->office_role);
        }
    }
}
