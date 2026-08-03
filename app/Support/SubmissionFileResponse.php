<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class SubmissionFileResponse
{
    public static function make(object $submission, Request $request)
    {
        $filePath = (string) ($submission->file_path ?? '');
        abort_unless(SecureUpload::isSafeRelativePath($filePath), 404);

        $disk = self::resolveDisk($filePath);
        abort_unless($disk !== null, 404);

        $pathExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $nameExtension = strtolower(pathinfo((string) ($submission->file_name ?? ''), PATHINFO_EXTENSION));
        $expectedMime = SecureUpload::mimeForExtension($pathExtension);
        $metadataMime = SecureUpload::mimeForExtension($nameExtension);
        $detectedMime = SecureUpload::normalizeMime((string) ($disk->mimeType($filePath) ?: ''));
        $storedMime = SecureUpload::normalizeMime((string) ($submission->file_type ?? ''));

        abort_unless(
            $expectedMime !== null
            && $metadataMime === $expectedMime
            && $detectedMime === $expectedMime
            && ($storedMime === '' || $storedMime === 'application/octet-stream' || $storedMime === $expectedMime),
            415
        );

        $downloadName = SecureUpload::safeDownloadName(
            (string) ($submission->file_name ?? ''),
            $pathExtension
        );
        $forceDownload = $request->boolean('download') || $request->boolean('dl');
        $headers = [
            'Content-Type' => $expectedMime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox; frame-ancestors 'self'",
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
        ];
        $path = $disk->path($filePath);

        $response = ! $forceDownload
            ? response()->file($path, $headers)->setContentDisposition('inline', $downloadName)
            : response()->download($path, $downloadName, $headers);

        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    private static function resolveDisk(string $filePath)
    {
        foreach (['local', 'public'] as $diskName) {
            $disk = Storage::disk($diskName);

            if ($disk->exists($filePath)) {
                return $disk;
            }
        }

        return null;
    }
}
