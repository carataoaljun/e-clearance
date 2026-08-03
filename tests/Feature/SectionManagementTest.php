<?php

namespace Tests\Feature;

use App\Models\MainAdmin;
use App\Models\ProgramSection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SectionManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('program_sections');
        Schema::create('program_sections', function (Blueprint $table) {
            $table->id();
            $table->string('program', 50);
            $table->string('year_level', 20);
            $table->string('section', 50);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['program', 'year_level', 'section'], 'unique_section');
        });

        $admin = new MainAdmin(['name' => 'Test Admin', 'email' => 'admin@example.com']);
        $admin->id = 1;
        $this->actingAs($admin, 'admin');
    }

    public function test_group_update_replaces_all_old_rows_and_keeps_selected_years(): void
    {
        $first = ProgramSection::create(['program' => 'BSIT', 'year_level' => 1, 'section' => 'A']);
        ProgramSection::create(['program' => 'BSIT', 'year_level' => 2, 'section' => 'A']);

        $response = $this->put(route('sections.update', $first->id), [
            'program' => 'BSBA',
            'section' => 'B',
            'year_levels' => [1, 3],
        ]);

        $response->assertRedirect(route('sections.index'));
        $this->assertDatabaseMissing('program_sections', ['program' => 'BSIT', 'section' => 'A']);
        $this->assertDatabaseHas('program_sections', ['program' => 'BSBA', 'year_level' => 1, 'section' => 'B']);
        $this->assertDatabaseHas('program_sections', ['program' => 'BSBA', 'year_level' => 3, 'section' => 'B']);
        $this->assertSame(2, ProgramSection::count());
    }

    public function test_duplicate_program_year_and_section_is_rejected(): void
    {
        ProgramSection::create(['program' => 'BSIT', 'year_level' => 1, 'section' => 'A']);

        $response = $this->from(route('sections.index'))->post(route('sections.store'), [
            'program' => 'BSIT',
            'section' => 'a',
            'year_levels' => [1],
        ]);

        $response->assertRedirect(route('sections.index'));
        $response->assertSessionHasErrors('section');
        $this->assertSame(1, ProgramSection::count());
    }

    public function test_at_least_one_year_level_is_required(): void
    {
        $response = $this->from(route('sections.index'))->post(route('sections.store'), [
            'program' => 'BSIT',
            'section' => 'A',
        ]);

        $response->assertRedirect(route('sections.index'));
        $response->assertSessionHasErrors('year_levels');
        $this->assertSame(0, ProgramSection::count());
    }
}
