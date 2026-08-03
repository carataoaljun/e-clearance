<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class SecureUpload
{
    public const MAX_KILOBYTES = 10240;

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
        $path = $file->storeAs($directory, $storedName, 'local');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The submission file could not be stored securely.');
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
}
