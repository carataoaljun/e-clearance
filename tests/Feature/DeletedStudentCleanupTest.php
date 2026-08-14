<?php

namespace Tests\Feature;

use App\Models\MainAdmin;
use App\Models\Registrar;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeletedStudentCleanupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['office_clearance_status', 'clearance_status', 'notifications', 'student_account'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('student_account', function (Blueprint $table) {
            $table->id();
            $table->string('student_id')->unique();
            $table->string('firstname');
            $table->string('middlename')->nullable();
            $table->string('lastname');
            $table->string('suffix')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('program');
            $table->string('year_level');
            $table->string('section');
            $table->string('student_type')->nullable();
        });
        Schema::create('office_clearance_status', function (Blueprint $table) {
            $table->id();
            $table->string('student_id');
            $table->string('office_role');
            $table->string('approver_id')->nullable();
            $table->string('status')->default('Pending');
            $table->timestamp('updated_at')->nullable();
            $table->unique(['student_id', 'office_role']);
        });
        Schema::create('clearance_status', function (Blueprint $table) {
            $table->id();
            $table->string('student_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('instructor_id');
            $table->string('status')->default('Pending');
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable();
            $table->string('recipient_role')->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(false);
            $table->string('notif_type')->nullable();
            $table->string('link_url')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        DB::table('student_account')->insert([
            'student_id' => 'S-001', 'firstname' => 'Ana', 'lastname' => 'Reyes',
            'program' => 'BSIT', 'year_level' => '1', 'section' => 'A',
        ]);
        DB::table('office_clearance_status')->insert([
            ['student_id' => 'S-001', 'office_role' => 'registrar', 'status' => 'Pending', 'updated_at' => now()],
            ['student_id' => 'S-001', 'office_role' => 'library', 'status' => 'Approved', 'updated_at' => now()],
        ]);
        DB::table('clearance_status')->insert([
            'student_id' => 'S-001', 'subject_id' => 20, 'instructor_id' => 'INS-2', 'status' => 'Approved',
        ]);
        DB::table('notifications')->insert([
            'user_id' => 'S-001', 'recipient_role' => 'student', 'message' => 'Your Registrar clearance was approved.',
        ]);
    }

    private function actAsRegistrar(): void
    {
        // The email matters: the portal header renders @section('user-label', $registrar->full_name
        // ?? $registrar->email), and Blade opens an unclosed output buffer when that value is null.
        $registrar = new Registrar(['registrar_id' => 'REG-1', 'email' => 'registrar@mcc.test']);
        $registrar->id = 1;
        $this->actingAs($registrar, 'registrar');
    }

    private function deleteStudent(string $studentId)
    {
        $admin = new MainAdmin(['email' => 'admin@mcc.test', 'name' => 'Admin']);
        $admin->id = 1;

        return $this->actingAs($admin, 'admin')
            ->delete(route('students.destroy', $studentId));
    }

    public function test_deleting_a_student_clears_its_clearance_records(): void
    {
        $this->deleteStudent('S-001');

        $this->assertSame(0, DB::table('student_account')->where('student_id', 'S-001')->count());
        $this->assertSame(0, DB::table('office_clearance_status')->where('student_id', 'S-001')->count(), 'office clearance rows outlived the student');
        $this->assertSame(0, DB::table('clearance_status')->where('student_id', 'S-001')->count(), 'subject clearance rows outlived the student');
        $this->assertSame(0, DB::table('notifications')->where('user_id', 'S-001')->count(), 'notifications outlived the student');
    }

    public function test_the_registrar_listing_survives_an_orphaned_clearance_row(): void
    {
        // The state every database is already in: the tables are MyISAM, so the
        // ON DELETE CASCADE in the migrations never fired for past deletions.
        DB::table('student_account')->where('student_id', 'S-001')->delete();

        $this->actAsRegistrar();
        $this->get(route('registrar.student-clearance'))
            ->assertOk()
            ->assertDontSee('S-001');
    }

    public function test_a_live_student_still_appears_in_the_registrar_listing(): void
    {
        $this->actAsRegistrar();
        $this->get(route('registrar.student-clearance'))
            ->assertOk()
            ->assertSee('S-001')
            ->assertSee('Reyes');
    }
}
