<?php

namespace Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityPreflightCommandTest extends TestCase
{
    public function test_preflight_rejects_a_stray_public_php_script(): void
    {
        $originalPublicPath = app()->publicPath();
        $temporaryPublicPath = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'e-clearance-preflight-'.bin2hex(random_bytes(8));

        mkdir($temporaryPublicPath, 0700, true);
        file_put_contents($temporaryPublicPath.DIRECTORY_SEPARATOR.'index.php', '<?php');
        file_put_contents($temporaryPublicPath.DIRECTORY_SEPARATOR.'info.php', '<?php');
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('local')->put(
            'student_submissions/STU-PREFLIGHT/database.sql',
            'SELECT * FROM users;',
        );
        app()->usePublicPath($temporaryPublicPath);

        try {
            $exitCode = Artisan::call('security:preflight', [
                '--document-root' => $temporaryPublicPath,
            ]);
            $output = Artisan::output();
        } finally {
            app()->usePublicPath($originalPublicPath);
            unlink($temporaryPublicPath.DIRECTORY_SEPARATOR.'info.php');
            unlink($temporaryPublicPath.DIRECTORY_SEPARATOR.'index.php');
            rmdir($temporaryPublicPath);
        }

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString(
            'Unexpected executable PHP files exist inside public/: info.php.',
            $output,
        );
        $this->assertStringContainsString(
            'Unsafe or unsupported files exist in private upload storage: student_submissions/STU-PREFLIGHT/database.sql.',
            $output,
        );
    }
}
