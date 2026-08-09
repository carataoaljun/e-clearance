<?php

namespace Tests\Feature;

use App\Support\SecureUpload;
use App\Support\SubmissionFileResponse;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Support\UploadFixtures;
use Tests\TestCase;

class SecureUploadSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_active_or_appended_pdf_content_is_rejected_before_storage(): void
    {
        foreach ([
            UploadFixtures::pdf('/OpenAction 2 0 R'),
            UploadFixtures::pdf().'<script>alert(1)</script>',
            str_replace('/OpenAction', '/Open#41ction', UploadFixtures::pdf('/OpenAction 2 0 R')),
        ] as $index => $contents) {
            try {
                SecureUpload::store(
                    UploadedFile::fake()->createWithContent("unsafe-{$index}.pdf", $contents),
                    'student_submissions/STU-SECURE',
                );

                $this->fail('Unsafe PDF content was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('submission_file', $exception->errors());
            }
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_uploaded_images_are_reencoded_to_remove_appended_payloads(): void
    {
        $payload = '<?php echo "should-not-survive";';
        $stored = SecureUpload::store(
            UploadedFile::fake()->createWithContent('proof.png', UploadFixtures::png().$payload),
            'office_submissions/STU-SECURE/library',
        );
        $contents = Storage::disk('local')->get($stored['path']);

        $this->assertStringNotContainsString($payload, $contents);
        $this->assertStringStartsWith("\x89PNG\r\n\x1A\n", $contents);
        $this->assertStringEndsWith("\x00\x00\x00\x00IEND\xAE\x42\x60\x82", $contents);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.png$/', basename($stored['path']));
    }

    public function test_unsafe_legacy_files_are_refused_at_download_time(): void
    {
        $path = 'student_submissions/STU-SECURE/legacy.pdf';
        Storage::disk('local')->put($path, UploadFixtures::pdf('/JavaScript 2 0 R'));

        try {
            SubmissionFileResponse::make((object) [
                'file_path' => $path,
                'file_name' => 'legacy.pdf',
                'file_type' => 'application/pdf',
            ], request());

            $this->fail('Unsafe legacy content was served.');
        } catch (HttpException $exception) {
            $this->assertSame(415, $exception->getStatusCode());
        }
    }

    public function test_public_disk_fallback_is_disabled_by_default(): void
    {
        $path = 'student_submissions/STU-SECURE/public-only.pdf';
        Storage::disk('public')->put($path, UploadFixtures::pdf());

        $this->expectException(NotFoundHttpException::class);

        SubmissionFileResponse::make((object) [
            'file_path' => $path,
            'file_name' => 'public-only.pdf',
            'file_type' => 'application/pdf',
        ], request());
    }

    public function test_legacy_migration_refuses_unsupported_files(): void
    {
        $path = 'student_submissions/STU-SECURE/database.sql';
        Storage::disk('public')->put($path, 'SELECT * FROM main_admin;');

        $exitCode = Artisan::call('system:migrate-private-uploads', ['--dry-run' => true]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString(
            "Skipped unsafe or unsupported upload: {$path}",
            Artisan::output(),
        );
        Storage::disk('public')->assertExists($path);
        Storage::disk('local')->assertMissing($path);
    }
}
