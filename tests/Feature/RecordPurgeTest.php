<?php

namespace Tests\Feature;

use App\Support\RecordPurge;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecordPurgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'student_account', 'clearance_status', 'instructor_assignment', 'instructor_remarks',
            'irregular_enrollment', 'student_submissions', 'office_clearance_status',
            'notifications', 'chat_messages', 'chat_support', 'esignatures',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('student_account', function (Blueprint $table) {
            $table->id();
            $table->string('student_id')->unique();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('program');
            $table->string('year_level');
            $table->string('section');
        });
        Schema::create('clearance_status', function (Blueprint $table) {
            $table->id();
            $table->string('student_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('instructor_id');
            $table->string('status')->default('Pending');
        });
        Schema::create('instructor_assignment', function (Blueprint $table) {
            $table->id('assignment_id');
            $table->string('instructor_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('program');
            $table->string('year_level');
            $table->string('section');
        });
        Schema::create('instructor_remarks', function (Blueprint $table) {
            $table->id();
            $table->string('student_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('instructor_id');
            $table->text('remarks')->nullable();
        });
        Schema::create('irregular_enrollment', function (Blueprint $table) {
            $table->id();
            $table->string('student_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('instructor_id');
        });
        Schema::create('student_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('student_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('instructor_id');
            $table->string('file_path')->nullable();
        });
        Schema::create('office_clearance_status', function (Blueprint $table) {
            $table->id();
            $table->string('student_id');
            $table->string('office_role');
            $table->string('approver_id')->nullable();
            $table->string('status')->default('Pending');
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable();
            $table->string('recipient_role')->nullable();
            $table->text('message')->nullable();
        });
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->string('sender_id');
            $table->string('sender_role');
            $table->string('receiver_id');
            $table->string('receiver_role');
            $table->text('message');
        });
        Schema::create('chat_support', function (Blueprint $table) {
            $table->id();
            $table->string('sender_id');
            $table->string('sender_role');
            $table->text('message');
        });
        Schema::create('esignatures', function (Blueprint $table) {
            $table->id();
            $table->string('signer_id');
            $table->string('signer_role');
            $table->longText('signature_data');
        });

        DB::table('student_account')->insert([
            ['student_id' => 'S-001', 'firstname' => 'Ana', 'lastname' => 'Reyes', 'program' => 'BSIT', 'year_level' => '1', 'section' => 'A'],
            ['student_id' => 'S-002', 'firstname' => 'Ben', 'lastname' => 'Cruz', 'program' => 'BSIT', 'year_level' => '1', 'section' => 'B'],
        ]);
        DB::table('clearance_status')->insert([
            ['student_id' => 'S-001', 'subject_id' => 20, 'instructor_id' => 'INS-1'],
            ['student_id' => 'S-002', 'subject_id' => 20, 'instructor_id' => 'INS-1'],
            ['student_id' => 'S-001', 'subject_id' => 30, 'instructor_id' => 'INS-2'],
        ]);
        DB::table('instructor_assignment')->insert([
            ['instructor_id' => 'INS-1', 'subject_id' => 20, 'program' => 'BSIT', 'year_level' => '1', 'section' => 'A'],
            ['instructor_id' => 'INS-1', 'subject_id' => 20, 'program' => 'BSIT', 'year_level' => '1', 'section' => 'B'],
            ['instructor_id' => 'INS-2', 'subject_id' => 30, 'program' => 'BSIT', 'year_level' => '1', 'section' => 'A'],
        ]);
        DB::table('office_clearance_status')->insert([
            ['student_id' => 'S-001', 'office_role' => 'library', 'approver_id' => 'OFF-1', 'status' => 'Approved'],
        ]);
        DB::table('notifications')->insert([
            ['user_id' => 'INS-1', 'recipient_role' => 'instructor', 'message' => 'A submission is waiting.'],
            ['user_id' => 'S-001', 'recipient_role' => 'student', 'message' => 'Your clearance was approved.'],
        ]);
        DB::table('esignatures')->insert([
            ['signer_id' => 'INS-1', 'signer_role' => 'instructor', 'signature_data' => 'data:image/png;base64,AAA'],
            ['signer_id' => 'OFF-1', 'signer_role' => 'library', 'signature_data' => 'data:image/png;base64,BBB'],
        ]);
        DB::table('chat_messages')->insert([
            ['sender_id' => 'S-001', 'sender_role' => 'student', 'receiver_id' => 'INS-1', 'receiver_role' => 'instructor', 'message' => 'Hello'],
            ['sender_id' => 'S-002', 'sender_role' => 'student', 'receiver_id' => 'INS-2', 'receiver_role' => 'instructor', 'message' => 'Hi'],
        ]);
    }

    public function test_deleting_an_instructor_clears_everything_that_instructor_owned(): void
    {
        RecordPurge::instructor('INS-1');

        $this->assertSame(0, DB::table('instructor_assignment')->where('instructor_id', 'INS-1')->count());
        $this->assertSame(0, DB::table('clearance_status')->where('instructor_id', 'INS-1')->count());
        $this->assertSame(0, DB::table('notifications')->where('user_id', 'INS-1')->count());
        $this->assertSame(0, DB::table('esignatures')->where('signer_id', 'INS-1')->count());
        $this->assertSame(0, DB::table('chat_messages')->where('receiver_id', 'INS-1')->count());

        // A second instructor's records are untouched.
        $this->assertSame(1, DB::table('clearance_status')->where('instructor_id', 'INS-2')->count());
        $this->assertSame(1, DB::table('instructor_assignment')->where('instructor_id', 'INS-2')->count());
        $this->assertSame(1, DB::table('esignatures')->where('signer_id', 'OFF-1')->count());
    }

    public function test_deleting_office_personnel_keeps_the_student_clearance_they_approved(): void
    {
        RecordPurge::officePersonnel('OFF-1');

        // The clearance record belongs to the student, not to the approver.
        $this->assertSame(1, DB::table('office_clearance_status')->where('student_id', 'S-001')->count());
        $this->assertSame(0, DB::table('esignatures')->where('signer_id', 'OFF-1')->count());
    }

    public function test_deleting_a_subject_clears_only_that_subjects_records(): void
    {
        RecordPurge::subject(20);

        $this->assertSame(0, DB::table('clearance_status')->where('subject_id', 20)->count());
        $this->assertSame(0, DB::table('instructor_assignment')->where('subject_id', 20)->count());
        $this->assertSame(1, DB::table('clearance_status')->where('subject_id', 30)->count());
    }

    public function test_deleting_an_assignment_only_touches_its_own_section(): void
    {
        $assignment = DB::table('instructor_assignment')
            ->where('instructor_id', 'INS-1')->where('section', 'A')->first();

        RecordPurge::instructorAssignment($assignment);

        // S-001 is in section A, S-002 is in section B and keeps its row.
        $this->assertSame(0, DB::table('clearance_status')->where('student_id', 'S-001')->where('subject_id', 20)->count());
        $this->assertSame(1, DB::table('clearance_status')->where('student_id', 'S-002')->where('subject_id', 20)->count());
    }

    public function test_purging_tolerates_tables_that_do_not_exist(): void
    {
        Schema::dropIfExists('esignatures');
        Schema::dropIfExists('chat_support');

        RecordPurge::instructor('INS-1', 'instructor@mcc.test');

        $this->assertSame(0, DB::table('instructor_assignment')->where('instructor_id', 'INS-1')->count());
    }
}
