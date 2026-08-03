<?php

namespace Tests\Feature;

use App\Models\InstructorAssignment;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InstructorAssignmentModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('instructor_assignment');

        Schema::create('instructor_assignment', function ($table) {
            $table->id('assignment_id');
            $table->string('instructor_id', 50);
            $table->unsignedBigInteger('subject_id');
            $table->string('program', 5);
            $table->string('year_level', 20)->nullable();
            $table->string('section', 20)->nullable();
            $table->timestamp('assigned_at')->useCurrent();
        });
    }

    public function test_instructor_assignment_can_be_created_with_assigned_at_timestamp(): void
    {
        $assignment = InstructorAssignment::create([
            'instructor_id' => '1111',
            'subject_id' => 1,
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'EAST',
        ]);

        $this->assertDatabaseHas('instructor_assignment', [
            'assignment_id' => $assignment->assignment_id,
            'instructor_id' => '1111',
        ]);
    }
}
