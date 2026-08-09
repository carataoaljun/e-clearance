<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class SecureUpload
{
    public const MAX_KILOBYTES = 10240;

    public const MAX_BYTES = self::MAX_KILOBYTES * 1024;

    public const MAX_IMAGE_WIDTH = 6000;

    public const MAX_IMAGE_HEIGHT = 6000;

    public const MAX_IMAGE_PIXELS = 16000000;

    public const MAX_SIGNATURE_BYTES = 200 * 1024;

    public const MAX_SIGNATURE_WIDTH = 2000;

    public const MAX_SIGNATURE_HEIGHT = 1000;

    private const MIME_BY_EXTENSION = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    /**
     * Validation rules for private clearance documents.
     *
     * Both the client extension and server-detected MIME type must be allowed.
     */
    public static function rules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'max:'.self::MAX_KILOBYTES,
            'mimes:pdf,jpg,jpeg,png',
            'mimetypes:application/pdf,image/jpeg,image/png',
            'extensions:pdf,jpg,jpeg,png',
        ];
    }

    /**
     * Store an allowed document under a random name on the private local disk.
     *
     * @return array{path: string, original_name: string, mime_type: string}
     */
    public static function store(UploadedFile $file, string $directory): array
    {
        $directory = self::safeDirectory($directory);
        $contents = self::readUploadedFile($file);
        $mimeType = self::normalizeMime((string) $file->getMimeType());
        $clientExtension = strtolower($file->getClientOriginalExtension());
        $expectedMime = self::mimeForExtension($clientExtension);

        if ($expectedMime === null || $mimeType !== $expectedMime) {
            throw ValidationException::withMessages([
                'submission_file' => 'The submission must be a valid PDF, JPG, JPEG, or PNG file.',
            ]);
        }

        $storedExtension = $mimeType === 'image/jpeg' ? 'jpg' : $clientExtension;
        $storedName = Str::uuid()->toString().'.'.$storedExtension;
        $path = $directory.'/'.$storedName;

        if ($mimeType === 'application/pdf') {
            if (! self::pdfContentIsSafe($contents)) {
                throw self::invalidDocument();
            }
        } else {
            $contents = self::normalizeImage(
                $contents,
                $mimeType,
                self::MAX_BYTES,
                self::MAX_IMAGE_WIDTH,
                self::MAX_IMAGE_HEIGHT,
                self::MAX_IMAGE_PIXELS,
                'submission_file',
            );
        }

        $disk = Storage::disk('local');

        try {
            $written = $disk->put($path, $contents, ['visibility' => 'private']);
            $storedMime = self::normalizeMime((string) ($disk->mimeType($path) ?: ''));

            if (! $written
                || ! $disk->exists($path)
                || $disk->size($path) !== strlen($contents)
                || $storedMime !== $mimeType
                || ! self::storedFileIsSafe($disk, $path, $mimeType)) {
                throw new RuntimeException('The submission file could not be verified after storage.');
            }
        } catch (Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }

        return [
            'path' => $path,
            'original_name' => self::safeDownloadName($file->getClientOriginalName(), $storedExtension),
            'mime_type' => $mimeType,
        ];
    }

    /**
     * Delete either a new private file or a legacy public file.
     */
    public static function delete(?string $path): void
    {
        if (! self::isSafeRelativePath($path)) {
            return;
        }

        foreach (['local', 'public'] as $diskName) {
            $disk = Storage::disk($diskName);

            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    public static function mimeForExtension(string $extension): ?string
    {
        return self::MIME_BY_EXTENSION[strtolower(ltrim($extension, '.'))] ?? null;
    }

    public static function normalizeMime(string $mimeType): string
    {
        $mimeType = strtolower(trim(strtok($mimeType, ';') ?: ''));

        return match ($mimeType) {
            'image/jpg', 'image/pjpeg' => 'image/jpeg',
            'application/x-pdf' => 'application/pdf',
            default => $mimeType,
        };
    }

    public static function normalizePngDataUri(string $dataUri): string
    {
        $prefix = 'data:image/png;base64,';

        if (! str_starts_with($dataUri, $prefix)) {
            throw self::invalidSignature();
        }

        $binary = base64_decode(substr($dataUri, strlen($prefix)), true);

        if ($binary === false || $binary === '' || strlen($binary) > self::MAX_SIGNATURE_BYTES) {
            throw self::invalidSignature();
        }

        $normalized = self::normalizeImage(
            $binary,
            'image/png',
            self::MAX_SIGNATURE_BYTES,
            self::MAX_SIGNATURE_WIDTH,
            self::MAX_SIGNATURE_HEIGHT,
            self::MAX_SIGNATURE_WIDTH * self::MAX_SIGNATURE_HEIGHT,
            'signature_data',
        );

        return $prefix.base64_encode($normalized);
    }

    public static function storedFileIsSafe(Filesystem $disk, string $path, string $mimeType): bool
    {
        try {
            $size = $disk->size($path);

            if ($size < 1 || $size > self::MAX_BYTES) {
                return false;
            }

            $contents = $disk->get($path);

            if (! is_string($contents) || strlen($contents) !== $size) {
                return false;
            }

            return match ($mimeType) {
                'application/pdf' => self::pdfContentIsSafe($contents),
                'image/jpeg', 'image/png' => self::imageContentIsSafe($contents, $mimeType),
                default => false,
            };
        } catch (Throwable) {
            return false;
        }
    }

    public static function safeDownloadName(?string $name, string $extension): string
    {
        $name = str_replace('\\', '/', (string) $name);
        $baseName = pathinfo($name, PATHINFO_FILENAME);
        $baseName = preg_replace('/[^\pL\pN _-]+/u', '_', $baseName) ?? '';
        $baseName = preg_replace('/\s+/u', ' ', $baseName) ?? '';
        $baseName = trim(Str::limit($baseName, 120, ''), ' _-');

        return ($baseName !== '' ? $baseName : 'submission')
            .'.'.strtolower(ltrim($extension, '.'));
    }

    public static function isSafeRelativePath(?string $path): bool
    {
        if (! is_string($path) || $path === '' || str_contains($path, "\0")) {
            return false;
        }

        $normalized = str_replace('\\', '/', $path);

        if (str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized)) {
            return false;
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..'
                || ! preg_match('/\A[A-Za-z0-9._ ()-]+\z/D', $segment)) {
                return false;
            }
        }

        return true;
    }

    private static function safeDirectory(string $directory): string
    {
        $directory = trim(str_replace('\\', '/', $directory), '/');

        if (! self::isSafeRelativePath($directory)) {
            throw new RuntimeException('The private upload directory is invalid.');
        }

        return $directory;
    }

    private static function readUploadedFile(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        $size = $file->getSize();

        if (! $file->isValid()
            || ! is_string($path)
            || $path === ''
            || ! is_file($path)
            || ! is_readable($path)
            || ! is_int($size)
            || $size < 1
            || $size > self::MAX_BYTES) {
            throw self::invalidDocument();
        }

        $contents = file_get_contents($path, false, null, 0, self::MAX_BYTES + 1);

        if (! is_string($contents) || strlen($contents) !== $size) {
            throw self::invalidDocument();
        }

        return $contents;
    }

    private static function pdfContentIsSafe(string $contents): bool
    {
        if (strlen($contents) < 12
            || ! preg_match('/\A%PDF-(?:1\.[0-9]|2\.0)[\r\n]/D', $contents)
            || ! preg_match('/%%EOF[\x20\t\r\n]*\z/D', $contents)) {
            return false;
        }

        $decodedNames = preg_replace_callback(
            '/#([0-9A-Fa-f]{2})/',
            static fn (array $match): string => chr((int) hexdec($match[1])),
            $contents,
        );

        if (! is_string($decodedNames)) {
            return false;
        }

        return preg_match(
            '/\/(?:AA|AcroForm|EmbeddedFile|Encrypt|GoToR|ImportData|JavaScript|JS|Launch|OpenAction|RichMedia|SubmitForm|XFA)\b/i',
            $decodedNames,
        ) !== 1;
    }

    private static function imageContentIsSafe(string $contents, string $mimeType): bool
    {
        $expectedImageType = $mimeType === 'image/jpeg' ? IMAGETYPE_JPEG : IMAGETYPE_PNG;
        $image = @getimagesizefromstring($contents);

        if ($image === false
            || ($image[2] ?? null) !== $expectedImageType
            || ($image[0] ?? 0) < 1
            || ($image[1] ?? 0) < 1
            || ($image[0] ?? 0) > self::MAX_IMAGE_WIDTH
            || ($image[1] ?? 0) > self::MAX_IMAGE_HEIGHT
            || ($image[0] ?? 0) > intdiv(self::MAX_IMAGE_PIXELS, (int) $image[1])) {
            return false;
        }

        return $mimeType === 'image/jpeg'
            ? str_starts_with($contents, "\xFF\xD8\xFF") && str_ends_with($contents, "\xFF\xD9")
            : str_starts_with($contents, "\x89PNG\r\n\x1A\n")
                && str_ends_with($contents, "\x00\x00\x00\x00IEND\xAE\x42\x60\x82");
    }

    private static function normalizeImage(
        string $contents,
        string $mimeType,
        int $maximumBytes,
        int $maximumWidth,
        int $maximumHeight,
        int $maximumPixels,
        string $field,
    ): string {
        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('Secure image processing is unavailable on this server.');
        }

        $expectedImageType = $mimeType === 'image/jpeg' ? IMAGETYPE_JPEG : IMAGETYPE_PNG;
        $imageInfo = @getimagesizefromstring($contents);
        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);

        if ($imageInfo === false
            || ($imageInfo[2] ?? null) !== $expectedImageType
            || $width < 1
            || $height < 1
            || $width > $maximumWidth
            || $height > $maximumHeight
            || $width > intdiv($maximumPixels, $height)) {
            throw self::invalidImage($field, $maximumBytes, $maximumWidth, $maximumHeight);
        }

        $image = @imagecreatefromstring($contents);

        if (! $image instanceof \GdImage) {
            throw self::invalidImage($field, $maximumBytes, $maximumWidth, $maximumHeight);
        }

        $bufferLevel = ob_get_level();
        ob_start();

        try {
            if ($mimeType === 'image/png') {
                imagealphablending($image, false);
                imagesavealpha($image, true);
                $encoded = @imagepng($image, null, 6);
            } else {
                $encoded = @imagejpeg($image, null, 90);
            }

            $normalized = ob_get_clean();
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            imagedestroy($image);
        }

        if (! $encoded
            || ! is_string($normalized)
            || $normalized === ''
            || strlen($normalized) > $maximumBytes
            || ! self::imageContentIsSafe($normalized, $mimeType)) {
            throw self::invalidImage($field, $maximumBytes, $maximumWidth, $maximumHeight);
        }

        return $normalized;
    }

    private static function invalidDocument(): ValidationException
    {
        return ValidationException::withMessages([
            'submission_file' => 'The submission must be a safe, non-empty PDF, JPG, JPEG, or PNG file no larger than 10 MB.',
        ]);
    }

    private static function invalidImage(
        string $field,
        int $maximumBytes,
        int $maximumWidth,
        int $maximumHeight,
    ): ValidationException {
        return ValidationException::withMessages([
            $field => sprintf(
                'The image must be valid, no larger than %d KB, and no more than %d by %d pixels.',
                intdiv($maximumBytes, 1024),
                $maximumWidth,
                $maximumHeight,
            ),
        ]);
    }

    private static function invalidSignature(): ValidationException
    {
        return ValidationException::withMessages([
            'signature_data' => 'The signature must be a valid PNG image no larger than 200 KB or 2000 by 1000 pixels.',
        ]);
    }
}
