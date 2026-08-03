<?php

namespace Tests\Feature;

use App\Http\Controllers\Instructors\DashboardController;
use App\Http\Controllers\Student\SubmissionRemarkController;
use App\Models\Instructor;
use App\Models\StudentAccount;
use App\Models\StudentSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionRemarkFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('notifications');
        Schema::dropIfExists('instructor_remarks');
        Schema::dropIfExists('student_submissions');
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
            $table->string('student_type')->nullable();
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

        Schema::create('student_submissions', function ($table) {
            $table->id();
            $table->string('student_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('instructor_id');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
        });

        Schema::create('instructor_remarks', function ($table) {
            $table->id();
            $table->string('student_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('instructor_id');
            $table->text('remark');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('notifications', function ($table) {
            $table->id();
            $table->string('user_id');
            $table->string('recipient_role')->nullable();
            $table->text('message');
            $table->string('notif_type')->nullable();
            $table->string('link_url')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function test_student_upload_and_download_work_for_submissions(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $student = StudentAccount::create([
            'student_id' => '2023-0387',
            'firstname' => 'Ariel',
            'lastname' => 'Garcia',
            'email' => 'ariel@example.com',
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

        DB::table('instructor_account')->insert([
            'instructor_id' => 'inst-1',
            'firstname' => 'Liza',
            'lastname' => 'Teacher',
            'email' => 'liza@example.com',
            'password' => bcrypt('secret'),
            'department' => 'BSIT',
        ]);

        DB::table('instructor_assignment')->insert([
            'instructor_id' => 'inst-1',
            'subject_id' => $subjectId,
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'East',
        ]);

        Auth::shouldReceive('guard')->with('student')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($student);

        $request = Request::create('/student/submission-remark/upload', 'POST', [
            'subject_id' => $subjectId,
            'instructor_id' => 'inst-1',
            'description' => 'Please review this file',
        ], [], [
            'submission_file' => UploadedFile::fake()->create('sample.pdf', 120, 'application/pdf'),
        ]);

        $controller = new SubmissionRemarkController;
        $response = $controller->upload($request);

        $this->assertTrue($response->isRedirect());
        $this->assertDatabaseHas('student_submissions', [
            'student_id' => $student->student_id,
            'subject_id' => $subjectId,
            'instructor_id' => 'inst-1',
            'description' => 'Please review this file',
        ]);
        $this->assertDatabaseHas('clearance_status', [
            'student_id' => $student->student_id,
            'subject_id' => $subjectId,
            'instructor_id' => 'inst-1',
            'status' => 'Pending',
        ]);

        $submission = StudentSubmission::query()->first();
        $downloadResponse = $controller->download($submission);

        $this->assertSame(200, $downloadResponse->getStatusCode());
        $this->assertSame('application/pdf', $downloadResponse->headers->get('Content-Type'));
    }

    public function test_student_and_instructor_views_render_latest_remarks_from_history(): void
    {
        $student = StudentAccount::create([
            'student_id' => 'student-2',
            'firstname' => 'Cris',
            'lastname' => 'Dela Cruz',
            'email' => 'cris@example.com',
            'password' => bcrypt('secret'),
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'East',
        ]);

        $subjectId = DB::table('subject_codes')->insertGetId([
            'subject_code' => 'IT202',
            'subject_description' => 'Database',
            'year_level' => '4',
            'program' => 'BSIT',
            'semester' => '1st',
        ]);

        DB::table('instructor_account')->insert([
            'instructor_id' => 'inst-2',
            'firstname' => 'Mina',
            'lastname' => 'Teacher',
            'email' => 'mina@example.com',
            'password' => bcrypt('secret'),
            'department' => 'BSIT',
        ]);

        DB::table('instructor_assignment')->insert([
            'instructor_id' => 'inst-2',
            'subject_id' => $subjectId,
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'East',
        ]);

        DB::table('clearance_status')->insert([
            'student_id' => $student->student_id,
            'subject_id' => $subjectId,
            'instructor_id' => 'inst-2',
            'status' => 'Pending',
            'remarks' => null,
        ]);

        DB::table('instructor_remarks')->insert([
            'student_id' => $student->student_id,
            'subject_id' => $subjectId,
            'instructor_id' => 'inst-2',
            'remark' => 'Please resubmit your supporting document.',
        ]);

        $this->actingAs($student, 'student');

        $studentResponse = (new SubmissionRemarkController)->index();
        $studentSubmissions = $studentResponse->getData()['submissions'];
        $this->assertCount(1, $studentSubmissions);
        $this->assertSame(
            'Please resubmit your supporting document.',
            $studentSubmissions->first()->clearance_remarks
        );
        $this->assertStringContainsString('Please resubmit your supporting document.', $studentResponse->render());

        $instructor = Instructor::where('instructor_id', 'inst-2')->firstOrFail();
        $this->actingAs($instructor, 'instructor');

        $instructorResponse = (new DashboardController)->clearance(new Request);
        $this->assertStringContainsString('Please resubmit your supporting document.', $instructorResponse->render());
    }

    public function test_instructor_remark_submission_persists_and_notifies_student(): void
    {
        $studentId = 'student-1';
        $subjectId = 99;
        $instructorId = 'inst-1';

        DB::table('student_account')->insert([
            'student_id' => $studentId,
            'firstname' => 'Bobby',
            'lastname' => 'Cruz',
            'email' => 'bobby@example.com',
            'password' => bcrypt('secret'),
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'East',
        ]);

        DB::table('subject_codes')->insert([
            'subject_id' => $subjectId,
            'subject_code' => 'IT099',
            'subject_description' => 'Review Subject',
            'year_level' => '4',
            'program' => 'BSIT',
            'semester' => '1st Semester',
        ]);

        $instructor = Instructor::create([
            'instructor_id' => $instructorId,
            'firstname' => 'Remark',
            'lastname' => 'Instructor',
            'email' => 'remark-instructor@example.com',
            'password' => 'StrongPassword1!',
            'department' => 'BSIT',
        ]);

        DB::table('instructor_assignment')->insert([
            'instructor_id' => $instructorId,
            'subject_id' => $subjectId,
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'East',
        ]);

        DB::table('clearance_status')->insert([
            'student_id' => $studentId,
            'subject_id' => $subjectId,
            'instructor_id' => $instructorId,
            'status' => 'Pending',
            'remarks' => null,
        ]);

        $this->actingAs($instructor, 'instructor');

        $request = Request::create('/instructor/remarks', 'POST', [
            'student_id' => $studentId,
            'subject_id' => $subjectId,
            'remark' => 'Please resubmit your supporting document.',
        ]);

        $controller = new DashboardController;
        $response = $controller->sendRemark($request);

        $this->assertTrue($response->getData()->success);
        $this->assertDatabaseHas('instructor_remarks', [
            'student_id' => $studentId,
            'subject_id' => $subjectId,
            'instructor_id' => $instructorId,
            'remark' => 'Please resubmit your supporting document.',
        ]);
        $this->assertDatabaseHas('clearance_status', [
            'student_id' => $studentId,
            'subject_id' => $subjectId,
            'instructor_id' => $instructorId,
            'remarks' => 'Please resubmit your supporting document.',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $studentId,
            'message' => 'Your instructor left a remark on a subject. Please check your submissions page.',
        ]);
    }
}
