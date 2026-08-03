<?php

namespace Tests\Feature;

use App\Models\SubjectCode;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SubjectCodeModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('subject_codes');

        Schema::create('subject_codes', function ($table) {
            $table->id('subject_id');
            $table->string('subject_code', 20)->unique();
            $table->text('subject_description')->nullable();
            $table->string('year_level', 10);
            $table->string('program', 50);
            $table->string('semester', 50);
        });
    }

    public function test_subject_code_can_be_created_without_timestamp_columns(): void
    {
        $subject = SubjectCode::create([
            'subject_code' => '1111',
            'subject_description' => 'Capstone',
            'year_level' => '4',
            'program' => 'BSIT',
            'semester' => '1st Semester',
        ]);

        $this->assertDatabaseHas('subject_codes', [
            'subject_id' => $subject->subject_id,
            'subject_code' => '1111',
        ]);
    }
}
