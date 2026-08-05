<?php

namespace Tests\Feature;

use App\Http\Middleware\StudentAuthenticate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StudentApplicationDownloadTest extends TestCase
{
    public function test_student_sidebar_contains_the_application_download_button_in_a_browser(): void
    {
        $this->registerStudentSidebarTestRoute();

        $this->withHeader('User-Agent', 'Mozilla/5.0 Chrome/127.0 Mobile')
            ->get('/_tests/student-application-sidebar')
            ->assertOk()
            ->assertSee(route('student.application.download'), false)
            ->assertSee('Download Application');
    }

    public function test_student_sidebar_hides_the_download_button_inside_the_android_app(): void
    {
        $this->registerStudentSidebarTestRoute();

        $this->withHeader('User-Agent', 'Mozilla/5.0 Android MCCStudentAndroid/1.0')
            ->get('/_tests/student-application-sidebar')
            ->assertOk()
            ->assertDontSee(route('student.application.download'), false)
            ->assertDontSee('Download Application');
    }

    public function test_application_download_requires_a_student_login(): void
    {
        $this->get(route('student.application.download'))
            ->assertRedirect(route('student.login'));
    }

    public function test_student_can_download_the_configured_apk(): void
    {
        $apkPath = tempnam(sys_get_temp_dir(), 'student-app-');
        file_put_contents($apkPath, 'test-apk');

        try {
            config([
                'student_application.apk_path' => $apkPath,
                'student_application.download_name' => 'Student-Portal.apk',
            ]);

            $this->withoutMiddleware(StudentAuthenticate::class)
                ->get(route('student.application.download'))
                ->assertOk()
                ->assertDownload('Student-Portal.apk')
                ->assertHeader('content-type', 'application/vnd.android.package-archive');
        } finally {
            @unlink($apkPath);
        }
    }

    public function test_missing_apk_returns_not_found(): void
    {
        config(['student_application.apk_path' => base_path('missing-student-application.apk')]);

        $this->withoutMiddleware(StudentAuthenticate::class)
            ->get(route('student.application.download'))
            ->assertNotFound();
    }

    private function registerStudentSidebarTestRoute(): void
    {
        Route::get('/_tests/student-application-sidebar', fn () => view('layouts.portal'))
            ->name('student.application-sidebar-test');
    }
}
