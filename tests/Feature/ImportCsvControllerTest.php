<?php

namespace Tests\Feature;

use App\Models\MainAdmin;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ImportCsvControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_import_normalizes_valid_data_and_safely_skips_invalid_and_malformed_rows(): void
    {
        $csv = implode("\n", [
            'student_id,firstname,lastname,email,password,program,year_level,section,student_type',
            '2026-0001,Ana,Reyes,ANA.REYES@EXAMPLE.TEST,StrongPass1!,bsit,1,a,regular',
            '2026-0002,Ben,Santos,ben.santos@example.test,StrongPass2!,BSCS,2,B,Regular',
            '2026-0003,Cara,Cruz,cara.cruz@example.test,StrongPass3!,BSIT,2,C,Irregular,unexpected',
        ]);

        $response = $this->postImport('students', $csv);

        $response
            ->assertOk()
            ->assertJsonPath('inserted', 1)
            ->assertJsonPath('skipped', 2);

        $student = Student::query()->sole();
        $this->assertSame('ana.reyes@example.test', $student->email);
        $this->assertSame('BSIT', $student->program);
        $this->assertSame('A', $student->section);
        $this->assertSame('Regular', $student->student_type);
        $this->assertTrue(Hash::check('StrongPass1!', $student->password));
    }

    public function test_import_rejects_duplicate_or_unknown_headers_before_writing(): void
    {
        $csv = implode("\n", [
            'student_id,firstname,lastname,email,email,password,program,year_level,section,unexpected',
            '2026-0001,Ana,Reyes,a@example.test,a@example.test,StrongPass1!,BSIT,1,A,value',
        ]);

        $this->postImport('students', $csv)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('csv_file');

        $this->assertDatabaseCount('student_account', 0);
    }

    public function test_import_rejects_binary_content_even_when_named_csv(): void
    {
        $this->postImport('registrar', "firstname,lastname,email,password\nAna,Reyes,a@example.test,StrongPass1!\0")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('csv_file');

        $this->assertDatabaseCount('registrar', 0);
    }

    public function test_row_limit_is_enforced_before_any_account_is_created(): void
    {
        $rows = ['firstname,lastname,email,password'];
        for ($row = 1; $row <= 2001; $row++) {
            $rows[] = "Test,Registrar,test{$row}@example.test,StrongPass1!";
        }

        $this->postImport('registrar', implode("\n", $rows))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('csv_file');

        $this->assertDatabaseCount('registrar', 0);
    }

    private function postImport(string $type, string $contents)
    {
        $admin = MainAdmin::find(DB::table('main_admin')->insertGetId([
            'email' => 'csv-admin@example.test',
            'password' => Hash::make('StrongAdminPassword1!'),
        ]));

        return $this->actingAs($admin, 'admin')->postJson(route('import.csv'), [
            'type' => $type,
            'csv_file' => UploadedFile::fake()->createWithContent('import.csv', $contents),
        ]);
    }
}
