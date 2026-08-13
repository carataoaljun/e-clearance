<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ApplicationDownloadController extends Controller
{
    /**
     * The APK committed under public/. Hard-coded rather than read from config so
     * the download still resolves when config('student_application.apk_path')
     * carries an absolute path from another machine or from a bootstrap/cache
     * config built before the APK moved into public/downloads.
     */
    private const BUNDLED_APK = 'downloads/MCC-e-Clearance-Student.apk';

    public function __invoke(): BinaryFileResponse
    {
        $apkPath = $this->resolveApkPath();

        abort_if($apkPath === null, 404);

        return response()->download(
            $apkPath,
            (string) config('student_application.download_name', 'MCC-e-Clearance-Student.apk'),
            [
                'Content-Type' => 'application/vnd.android.package-archive',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    /**
     * Return the first readable APK, preferring the configured path and falling
     * back to the copy shipped in public/.
     */
    private function resolveApkPath(): ?string
    {
        $candidates = [config('student_application.apk_path'), self::BUNDLED_APK];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $path = $this->toAbsolutePath(trim($candidate));

            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Resolve a relative candidate against public/ so configured values stay
     * portable between the local WAMP checkout and the deployed host.
     */
    private function toAbsolutePath(string $candidate): string
    {
        return $this->isAbsolutePath($candidate) ? $candidate : public_path($candidate);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
