<?php

namespace App\Console\Commands;

use App\Support\SecureUpload;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;

class SecurityPreflight extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:preflight
        {--document-root= : Absolute document root from the production web-server configuration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fail deployment when required production security settings are unsafe';

    /** @var array<int, string> */
    private array $passes = [];

    /** @var array<int, string> */
    private array $errors = [];

    /** @var array<int, string> */
    private array $warnings = [];

    /** @var array<int, string> */
    private array $manualChecks = [];

    public function handle(): int
    {
        $this->passes = [];
        $this->errors = [];
        $this->warnings = [];
        $this->manualChecks = [];

        $this->checkApplicationConfiguration();
        $this->checkSessionConfiguration();
        $this->checkBackupConfiguration();
        $this->checkDocumentRoot();
        $this->addManualChecks();

        $this->renderResults();

        if ($this->errors !== []) {
            $this->newLine();
            $this->error(sprintf(
                'Security preflight failed with %d blocking issue(s). Do not deploy.',
                count($this->errors),
            ));

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Automated security preflight passed. Complete every MANUAL check before launch.');

        return self::SUCCESS;
    }

    private function checkApplicationConfiguration(): void
    {
        $this->assert(
            app()->environment('production'),
            'Application environment is production.',
            'APP_ENV must be production.',
        );
        $this->assert(
            config('app.debug') === false,
            'Application debug mode is disabled.',
            'APP_DEBUG must be false.',
        );
        $this->assert(
            extension_loaded('fileinfo'),
            'The Fileinfo extension is available for server-side MIME detection.',
            'The PHP Fileinfo extension is required for secure upload MIME detection.',
        );
        $this->assert(
            extension_loaded('gd'),
            'The GD extension is available for image decoding and re-encoding.',
            'The PHP GD extension is required to sanitize uploaded images and signatures.',
        );
        $this->assert(
            config('security.force_https') === true,
            'Application HTTPS enforcement is enabled.',
            'FORCE_HTTPS must be true in production.',
        );
        $this->assert(
            app()->configurationIsCached(),
            'Laravel configuration is cached.',
            'Configuration is not cached; run php artisan optimize after setting production environment values.',
        );
        $this->assert(
            $this->validApplicationKey(),
            'APP_KEY is present and valid for the configured cipher.',
            'APP_KEY is missing, malformed, or incompatible with the configured cipher.',
        );

        $url = (string) config('app.url');
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $placeholderHost = $host === ''
            || in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.localhost')
            || str_contains($host, 'example.');

        $this->assert(
            $scheme === 'https' && ! $placeholderHost,
            'APP_URL uses HTTPS and a non-placeholder host.',
            'APP_URL must use https:// and the real production hostname.',
        );

        $debugLogLevels = [];

        foreach ((array) config('logging.channels', []) as $channel => $configuration) {
            if (
                is_array($configuration)
                && strtolower((string) ($configuration['level'] ?? '')) === 'debug'
            ) {
                $debugLogLevels[] = (string) $channel;
            }
        }

        $this->assert(
            $debugLogLevels === [],
            'No configured log channel uses the debug level.',
            'Production log channels must not use LOG_LEVEL=debug.',
        );
    }

    private function checkSessionConfiguration(): void
    {
        $this->assert(
            config('session.secure') === true,
            'Session cookies require HTTPS.',
            'SESSION_SECURE_COOKIE must be true.',
        );
        $this->assert(
            config('session.http_only') === true,
            'Session cookies are HTTP-only.',
            'SESSION_HTTP_ONLY must be true.',
        );
        $this->assert(
            in_array(strtolower((string) config('session.same_site')), ['lax', 'strict'], true),
            'Session SameSite policy is lax or strict.',
            'SESSION_SAME_SITE must be lax or strict for this same-site application.',
        );
        $this->assert(
            config('session.encrypt') === true,
            'Session payload encryption is enabled.',
            'SESSION_ENCRYPT must be true in production.',
        );
        $this->assert(
            config('session.driver') !== 'array',
            'Session storage is persistent.',
            'The array session driver is not allowed in production.',
        );
    }

    private function checkBackupConfiguration(): void
    {
        $this->assert(
            config('backup.enabled') === true,
            'Scheduled backups are enabled.',
            'BACKUP_ENABLED must be true.',
        );

        $diskName = trim((string) config('backup.disk'));
        $disk = config('filesystems.disks.'.$diskName);
        $driver = is_array($disk) ? strtolower((string) ($disk['driver'] ?? '')) : '';

        $this->assert(
            $diskName !== '' && is_array($disk),
            'The configured backup filesystem disk exists.',
            'BACKUP_DISK does not name a configured filesystem disk.',
        );
        $this->assert(
            $driver !== '' && $driver !== 'local',
            'The configured backup disk is non-local.',
            'BACKUP_DISK must use off-host storage; the local disk is not an offsite backup.',
        );

        if ($driver === 's3' && is_array($disk)) {
            $s3Key = trim((string) ($disk['key'] ?? ''));
            $s3Secret = trim((string) ($disk['secret'] ?? ''));
            $credentialPairIsValid = ($s3Key === '' && $s3Secret === '')
                || ($s3Key !== '' && $s3Secret !== '');
            $this->assert(
                $this->allConfigured($disk, ['region', 'bucket']) && $credentialPairIsValid,
                'Required S3 backup settings are configured.',
                'The S3 backup disk requires a region, bucket, and either an IAM role or a complete key/secret pair.',
            );
            $this->assert(
                class_exists(AwsS3V3Adapter::class),
                'The S3 Flysystem adapter is installed.',
                'The S3 backup disk is selected, but its Flysystem adapter is not installed.',
            );
        }

        $backupPath = trim(str_replace('\\', '/', (string) config('backup.path')), '/');
        $this->assert(
            $backupPath !== '' && ! in_array('..', explode('/', $backupPath), true),
            'The backup destination path is non-empty and traversal-free.',
            'BACKUP_PATH is empty or contains an unsafe parent-directory segment.',
        );

        $includePaths = array_filter(
            (array) config('backup.include', []),
            fn (mixed $path): bool => is_string($path) && trim($path) !== '',
        );
        $privatePath = $this->normalizedPath(storage_path('app/private'));
        $publicPath = $this->normalizedPath(storage_path('app/public'));
        $normalizedIncludes = array_map(
            fn (string $path): string => $this->normalizedPath($path),
            $includePaths,
        );

        $this->assert(
            in_array($privatePath, $normalizedIncludes, true),
            'Private application storage is included in backups.',
            'storage/app/private must be present in the backup include paths.',
        );

        if (! in_array($publicPath, $normalizedIncludes, true)) {
            $this->warnings[] = 'Public application storage is excluded. Confirm that it contains no uploaded requirements.';
        }

        $retentionDays = (int) config('backup.retention.days');
        $retentionCount = (int) config('backup.retention.maximum_backups');
        $this->assert(
            $retentionDays > 0 || $retentionCount > 0,
            'Backup retention has an age or count limit.',
            'Configure a positive backup retention period or maximum archive count.',
        );

        $scheduleTime = (string) config('backup.schedule.time');
        $this->assert(
            preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $scheduleTime) === 1,
            'The daily backup time is valid.',
            'BACKUP_SCHEDULE_TIME must use 24-hour HH:MM format.',
        );

        try {
            new DateTimeZone((string) config('backup.schedule.timezone'));
            $validTimezone = true;
        } catch (\Exception) {
            $validTimezone = false;
        }

        $this->assert(
            $validTimezone,
            'The backup schedule timezone is valid.',
            'BACKUP_SCHEDULE_TIMEZONE is invalid.',
        );

        $connectionName = trim((string) config('backup.database_connection'));
        $connection = config('database.connections.'.$connectionName);
        $databaseDriver = is_array($connection)
            ? strtolower((string) ($connection['driver'] ?? ''))
            : '';

        $this->assert(
            in_array($databaseDriver, ['mysql', 'mariadb', 'sqlite'], true),
            'The backup database driver is supported.',
            'The backup command supports only MySQL, MariaDB, and SQLite connections.',
        );

        $temporaryPath = trim((string) config('backup.temporary_path'));
        $this->assert(
            $temporaryPath !== '' && ! $this->pathIsWithin($temporaryPath, public_path()),
            'Backup staging is outside the public directory.',
            'The backup temporary path must be outside Laravel public/.',
        );

        if (trim((string) config('backup.archive.password')) === '') {
            $this->warnings[] = 'BACKUP_ARCHIVE_PASSWORD is empty. Use provider-side encryption or an archive password kept outside the repository.';
        }
    }

    private function checkDocumentRoot(): void
    {
        $provided = $this->option('document-root');
        $provided = is_string($provided) ? trim($provided) : '';

        if ($provided === '') {
            $serverDocumentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
            $provided = is_string($serverDocumentRoot) ? trim($serverDocumentRoot) : '';
        }

        if ($provided === '') {
            $this->manualChecks[] = 'Run this command with --document-root=/absolute/path/to/project/public, using the value from the production web-server configuration.';
        } else {
            $this->assert(
                $this->pathsEqual($provided, public_path()),
                'The supplied web document root is exactly Laravel public/.',
                'The production document root must be exactly '.public_path().'.',
            );
        }

        $this->assert(
            is_file(base_path('.htaccess')),
            'A root fail-closed .htaccess defense is present.',
            'The project-root fail-closed .htaccess file is missing.',
        );
        $this->assert(
            ! is_file(public_path('.env')),
            'No .env file exists inside public/.',
            'A .env file exists inside public/ and must be removed immediately.',
        );
        $this->assert(
            config('uploads.allow_legacy_public_files') === false,
            'Legacy public-upload fallback is disabled.',
            'UPLOAD_ALLOW_LEGACY_PUBLIC_FILES must be false after migrating legacy uploads.',
        );

        $legacyPublicUploads = collect(['student_submissions', 'office_submissions'])
            ->flatMap(fn (string $directory) => Storage::disk('public')->allFiles($directory))
            ->unique()
            ->values()
            ->all();
        $this->assert(
            $legacyPublicUploads === [],
            'No clearance submissions remain on the public filesystem disk.',
            'Legacy clearance submissions remain public: '.implode(', ', array_slice($legacyPublicUploads, 0, 10)).'. Run system:migrate-private-uploads.',
        );

        $unsafePrivateUploads = $this->unsafePrivateUploads();
        $this->assert(
            $unsafePrivateUploads === [],
            'Existing private clearance uploads pass content and type inspection.',
            'Unsafe or unsupported files exist in private upload storage: '.implode(', ', array_slice($unsafePrivateUploads, 0, 10)).'. Quarantine and investigate them.',
        );

        $unexpectedPhpFiles = $this->unexpectedPublicPhpFiles();
        $this->assert(
            $unexpectedPhpFiles === [],
            'public/index.php is the only executable PHP file inside public/.',
            'Unexpected executable PHP files exist inside public/: '.implode(', ', $unexpectedPhpFiles).'. Remove them before deployment.',
        );
    }

    private function addManualChecks(): void
    {
        $this->manualChecks[] = 'Verify the real HTTPS certificate chain, expiry, HTTP-to-HTTPS redirect, HSTS header, and proxy forwarding at the public URL.';
        $this->manualChecks[] = 'Request /.env, /info.php, /phpinfo.php, /public/info.php, /vendor/composer/installed.json, /storage/logs/laravel.log, and /app/ over the public hostname; every request must be denied or not found.';
        $this->manualChecks[] = 'Confirm the scheduler invokes php artisan schedule:run every minute and inspect php artisan schedule:list.';
        $this->manualChecks[] = 'Confirm the backup disk belongs to a separate hosting/provider account, then restore the newest archive into an isolated environment.';
        $this->manualChecks[] = 'Confirm .env was never committed; rotate every secret that was previously shared or published.';
    }

    private function renderResults(): void
    {
        foreach ($this->passes as $message) {
            $this->line('<info>PASS</info>   '.$message);
        }

        foreach ($this->errors as $message) {
            $this->line('<error>FAIL</error>   '.$message);
        }

        foreach ($this->warnings as $message) {
            $this->line('<comment>WARN</comment>   '.$message);
        }

        foreach ($this->manualChecks as $message) {
            $this->line('<comment>MANUAL</comment> '.$message);
        }
    }

    private function assert(bool $condition, string $pass, string $failure): void
    {
        if ($condition) {
            $this->passes[] = $pass;
        } else {
            $this->errors[] = $failure;
        }
    }

    private function validApplicationKey(): bool
    {
        $key = (string) config('app.key');
        $cipher = (string) config('app.cipher');

        if ($key === '' || $cipher === '') {
            return false;
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded === false) {
                return false;
            }

            $key = $decoded;
        }

        return Encrypter::supported($key, $cipher);
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @param  array<int, string>  $keys
     */
    private function allConfigured(array $configuration, array $keys): bool
    {
        foreach ($keys as $key) {
            if (! isset($configuration[$key]) || trim((string) $configuration[$key]) === '') {
                return false;
            }
        }

        return true;
    }

    private function pathsEqual(string $left, string $right): bool
    {
        return $this->normalizedPath($left) === $this->normalizedPath($right);
    }

    /** @return array<int, string> */
    private function unexpectedPublicPhpFiles(): array
    {
        $publicDirectory = public_path();

        if (! is_dir($publicDirectory)) {
            return ['[public directory is missing]'];
        }

        $unexpected = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $publicDirectory,
                \FilesystemIterator::SKIP_DOTS,
            ),
        );
        $normalizedPublicDirectory = $this->normalizedPath($publicDirectory);

        foreach ($iterator as $file) {
            if (
                ! $file->isFile()
                || preg_match('/\.(?:php(?:\d*|s)|phtml|phar)$/i', $file->getFilename()) !== 1
            ) {
                continue;
            }

            $path = $this->normalizedPath($file->getPathname());
            $relativePath = ltrim(substr($path, strlen($normalizedPublicDirectory)), '/');

            if ($relativePath !== 'index.php') {
                $unexpected[] = $relativePath;
            }
        }

        sort($unexpected, SORT_STRING);

        return $unexpected;
    }

    /** @return array<int, string> */
    private function unsafePrivateUploads(): array
    {
        $disk = Storage::disk('local');
        $paths = collect(['student_submissions', 'office_submissions'])
            ->flatMap(fn (string $directory) => $disk->allFiles($directory))
            ->unique()
            ->values();
        $unsafe = [];

        foreach ($paths as $path) {
            try {
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $expectedMime = SecureUpload::mimeForExtension($extension);
                $detectedMime = SecureUpload::normalizeMime((string) ($disk->mimeType($path) ?: ''));
            } catch (\Throwable) {
                $expectedMime = null;
                $detectedMime = '';
            }

            if ($expectedMime === null
                || $detectedMime !== $expectedMime
                || ! SecureUpload::storedFileIsSafe($disk, $path, $expectedMime)) {
                $unsafe[] = $path;
            }
        }

        sort($unsafe, SORT_STRING);

        return $unsafe;
    }

    private function pathIsWithin(string $path, string $parent): bool
    {
        $path = $this->normalizedPath($path);
        $parent = $this->normalizedPath($parent);

        return $path === $parent || str_starts_with($path, $parent.'/');
    }

    private function normalizedPath(string $path): string
    {
        $path = realpath($path) ?: $path;
        $path = rtrim(str_replace('\\', '/', $path), '/');

        return PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;
    }
}
