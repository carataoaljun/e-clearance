<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ApplicationDownloadController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $apkPath = config('student_application.apk_path');

        abort_unless(is_string($apkPath) && is_file($apkPath) && is_readable($apkPath), 404);

        return response()->download(
            $apkPath,
            (string) config('student_application.download_name'),
            [
                'Content-Type' => 'application/vnd.android.package-archive',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
