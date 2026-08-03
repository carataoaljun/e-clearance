<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SQLite3;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class CreateSystemBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:system
        {--connection= : Database connection to back up}
        {--disk= : Filesystem disk on which to store the archive}
        {--no-retention : Keep all existing backup archives}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a database and application-file backup on the configured backup disk';

    public function handle(Filesystem $files): int
    {
        $workingDirectory = null;
        $temporaryRoot = null;

        try {
            $this->assertZipSupport();

            $connection = $this->optionString('connection')
                ?: trim((string) config('backup.database_connection'));
            $diskName = $this->optionString('disk')
                ?: trim((string) config('backup.disk'));

            if ($connection === '') {
                throw new RuntimeException('No backup database connection is configured.');
            }

            if ($diskName === '') {
                throw new RuntimeException('No backup filesystem disk is configured.');
            }

            $prefix = $this->validatedBackupPrefix();
            $temporaryRoot = $this->validatedTemporaryRoot();

            if ($this->pathIsWithin($temporaryRoot, public_path())) {
                throw new RuntimeException('The backup temporary path must be outside Laravel public/.');
            }

            $files->ensureDirectoryExists($temporaryRoot, 0700, true);
            $temporaryRoot = realpath($temporaryRoot) ?: $temporaryRoot;

            if ($this->pathIsWithin($temporaryRoot, public_path())) {
                throw new RuntimeException('The backup temporary path resolves inside Laravel public/.');
            }

            $workingDirectory = $temporaryRoot.DIRECTORY_SEPARATOR.'system-backup-'.Str::uuid();
            $files->ensureDirectoryExists($workingDirectory, 0700, true);

            [$databaseDriver, $databaseDump] = $this->createDatabaseDump(
                $connection,
                $workingDirectory,
                $files,
            );

            $timestamp = now('UTC')->format('Ymd\THis\Z');
            $prefixName = $this->archiveNamePrefix();
            $archiveName = $prefixName.'-'.$timestamp.'-'.Str::lower(Str::random(8)).'.zip';
            $archivePath = $workingDirectory.DIRECTORY_SEPARATOR.$archiveName;

            $fileCount = $this->createArchive(
                archivePath: $archivePath,
                databaseDump: $databaseDump,
                databaseDriver: $databaseDriver,
                connection: $connection,
                diskName: $diskName,
                diskPrefix: $prefix,
            );

            @chmod($archivePath, 0600);

            $disk = Storage::disk($diskName);
            $target = $prefix.'/'.$archiveName;
            $localSize = $files->size($archivePath);

            if ($disk->exists($target)) {
                throw new RuntimeException('The generated backup object name already exists; no data was overwritten.');
            }

            $stream = fopen($archivePath, 'rb');

            if ($stream === false) {
                throw new RuntimeException('The completed backup archive could not be opened for upload.');
            }

            try {
                try {
                    if (! $disk->writeStream($target, $stream)) {
                        throw new RuntimeException('The backup disk rejected the archive upload.');
                    }
                } catch (Throwable $exception) {
                    $this->deleteIncompleteUpload($disk, $target);
                    throw $exception;
                }
            } finally {
                fclose($stream);
            }

            try {
                $uploadVerified = $disk->exists($target) && $disk->size($target) === $localSize;
            } catch (Throwable $exception) {
                $this->deleteIncompleteUpload($disk, $target);
                throw $exception;
            }

            if (! $uploadVerified) {
                $this->deleteIncompleteUpload($disk, $target);
                throw new RuntimeException('The uploaded backup archive failed the size verification check.');
            }

            if (! $this->option('no-retention')) {
                $this->applyRetention($disk, $prefix, $target);
            }

            $this->info(sprintf(
                'Backup completed: disk=%s path=%s files=%d bytes=%d',
                $diskName,
                $target,
                $fileCount,
                $localSize,
            ));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            logger()->error('System backup failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $this->error('Backup failed: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if (
                is_string($workingDirectory)
                && is_string($temporaryRoot)
                && str_starts_with(basename($workingDirectory), 'system-backup-')
                && $this->pathIsWithin($workingDirectory, $temporaryRoot)
            ) {
                $files->deleteDirectory($workingDirectory);
            }
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createDatabaseDump(
        string $connection,
        string $workingDirectory,
        Filesystem $files,
    ): array {
        $connectionConfig = config('database.connections.'.$connection);

        if (! is_array($connectionConfig)) {
            throw new RuntimeException("Database connection [{$connection}] is not configured.");
        }

        try {
            $connectionConfig = (new ConfigurationUrlParser)->parseConfiguration($connectionConfig);
        } catch (Throwable $exception) {
            throw new RuntimeException('The backup database connection URL is invalid.', 0, $exception);
        }

        $driver = strtolower(trim((string) ($connectionConfig['driver'] ?? '')));

        return match ($driver) {
            'mysql', 'mariadb' => [
                $driver,
                $this->createMysqlDump($connectionConfig, $workingDirectory, $files),
            ],
            'sqlite' => [
                $driver,
                $this->createSqliteBackup($connectionConfig, $workingDirectory, $files),
            ],
            default => throw new RuntimeException(
                "Database driver [{$driver}] is not supported; use MySQL, MariaDB, or SQLite.",
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function createMysqlDump(
        array $connection,
        string $workingDirectory,
        Filesystem $files,
    ): string {
        $database = trim((string) ($connection['database'] ?? ''));
        $username = trim((string) ($connection['username'] ?? ''));
        $binary = trim((string) config('backup.mysql.dump_binary', 'mysqldump'));

        if ($database === '' || $username === '' || $binary === '') {
            throw new RuntimeException('The MySQL backup connection or dump binary is incomplete.');
        }

        $dumpPath = $workingDirectory.DIRECTORY_SEPARATOR.'database.sql';
        $arguments = [
            $binary,
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--events',
            '--default-character-set=utf8mb4',
            '--user='.$username,
            '--result-file='.$dumpPath,
        ];

        if ((bool) config('backup.mysql.no_tablespaces', true)) {
            $arguments[] = '--no-tablespaces';
        }

        $socket = trim((string) ($connection['unix_socket'] ?? ''));

        if ($socket !== '') {
            $arguments[] = '--socket='.$socket;
        } else {
            $host = trim((string) ($connection['host'] ?? '127.0.0.1'));
            $port = (int) ($connection['port'] ?? 3306);

            if ($host === '' || $port < 1 || $port > 65535) {
                throw new RuntimeException('The MySQL backup host or port is invalid.');
            }

            $arguments[] = '--host='.$host;
            $arguments[] = '--port='.$port;
        }

        $arguments[] = $database;

        // MYSQL_PWD keeps the secret out of process arguments and process lists.
        // The Process component receives an argument array and never invokes a shell.
        $process = new Process(
            $arguments,
            base_path(),
            ['MYSQL_PWD' => (string) ($connection['password'] ?? '')],
        );
        $process->setTimeout(max(1, (int) config('backup.mysql.timeout', 900)));
        $process->run();

        if (! $process->isSuccessful()) {
            $detail = trim($process->getErrorOutput());
            $detail = $detail !== '' ? preg_replace('/\s+/', ' ', $detail) : 'no error output';

            throw new RuntimeException('mysqldump failed: '.Str::limit((string) $detail, 1000));
        }

        if (! $files->isFile($dumpPath) || $files->size($dumpPath) < 1) {
            throw new RuntimeException('mysqldump completed without producing a database dump.');
        }

        @chmod($dumpPath, 0600);

        return $dumpPath;
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function createSqliteBackup(
        array $connection,
        string $workingDirectory,
        Filesystem $files,
    ): string {
        if (! class_exists(SQLite3::class)) {
            throw new RuntimeException('The PHP SQLite3 extension is required to back up SQLite safely.');
        }

        $configuredPath = trim((string) ($connection['database'] ?? ''));

        if ($configuredPath === '' || $configuredPath === ':memory:') {
            throw new RuntimeException('An in-memory or missing SQLite database cannot be backed up.');
        }

        $sourcePath = $this->isAbsolutePath($configuredPath)
            ? $configuredPath
            : database_path($configuredPath);
        $sourcePath = realpath($sourcePath) ?: $sourcePath;

        if (! $files->isFile($sourcePath)) {
            throw new RuntimeException('The configured SQLite database file does not exist.');
        }

        $dumpPath = $workingDirectory.DIRECTORY_SEPARATOR.'database.sqlite';
        $source = null;
        $destination = null;

        try {
            $source = new SQLite3($sourcePath, SQLITE3_OPEN_READONLY);
            $destination = new SQLite3($dumpPath, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
            $source->busyTimeout(30_000);
            $destination->busyTimeout(30_000);

            if (! $source->backup($destination)) {
                throw new RuntimeException('SQLite rejected the online backup operation.');
            }
        } finally {
            $destination?->close();
            $source?->close();
        }

        if (! $files->isFile($dumpPath) || $files->size($dumpPath) < 1) {
            throw new RuntimeException('SQLite completed without producing a database backup.');
        }

        @chmod($dumpPath, 0600);

        return $dumpPath;
    }

    private function createArchive(
        string $archivePath,
        string $databaseDump,
        string $databaseDriver,
        string $connection,
        string $diskName,
        string $diskPrefix,
    ): int {
        $zip = new ZipArchive;
        $opened = $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            throw new RuntimeException("The ZIP archive could not be created (code {$opened}).");
        }

        $fileCount = 0;
        $includedRoots = [];

        try {
            $databaseEntry = 'database/'.basename($databaseDump);
            $this->addFileToArchive($zip, $databaseDump, $databaseEntry);
            $fileCount++;

            $exclusions = array_filter(
                (array) config('backup.exclude', []),
                fn (mixed $path): bool => is_string($path) && trim($path) !== '',
            );
            $exclusions[] = $this->validatedTemporaryRoot();

            $diskConfig = config('filesystems.disks.'.$diskName);

            if (is_array($diskConfig) && ($diskConfig['driver'] ?? null) === 'local') {
                $diskRoot = trim((string) ($diskConfig['root'] ?? ''));

                if ($diskRoot !== '') {
                    $exclusions[] = $diskRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $diskPrefix);
                }
            }

            $roots = (array) config('backup.include', []);

            if ($roots === []) {
                throw new RuntimeException('No application file paths are configured for backup.');
            }

            foreach ($roots as $index => $root) {
                if (! is_string($root) || trim($root) === '') {
                    throw new RuntimeException('A configured backup include path is invalid.');
                }

                $canonicalRoot = realpath($root);

                if ($canonicalRoot === false || ! is_dir($canonicalRoot)) {
                    throw new RuntimeException("Backup include path [{$root}] is not a readable directory.");
                }

                $archiveRoot = $this->archiveRootName($canonicalRoot, (int) $index);
                $includedRoots[] = $archiveRoot;

                if (! $zip->addEmptyDir($archiveRoot)) {
                    throw new RuntimeException("Archive root [{$archiveRoot}] could not be created.");
                }

                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(
                        $canonicalRoot,
                        RecursiveDirectoryIterator::SKIP_DOTS,
                    ),
                    RecursiveIteratorIterator::LEAVES_ONLY,
                );

                foreach ($iterator as $file) {
                    if ($file->isLink()) {
                        throw new RuntimeException(
                            "Symbolic link [{$file->getPathname()}] is not allowed in a backup include path.",
                        );
                    }

                    if (! $file->isFile()) {
                        continue;
                    }

                    $filePath = $file->getRealPath();

                    if ($filePath === false || $this->isExcluded($filePath, $exclusions)) {
                        continue;
                    }

                    $relative = ltrim(substr($filePath, strlen($canonicalRoot)), '\\/');
                    $entry = $archiveRoot.'/'.str_replace('\\', '/', $relative);
                    $this->addFileToArchive($zip, $filePath, $entry);
                    $fileCount++;
                }
            }

            $metadata = json_encode([
                'format_version' => 1,
                'created_at' => now('UTC')->toIso8601String(),
                'application' => (string) config('app.name'),
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
                'database_driver' => $databaseDriver,
                'database_connection' => $connection,
                'included_roots' => $includedRoots,
                'file_count' => $fileCount,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            if (! $zip->addFromString('backup-metadata.json', $metadata)) {
                throw new RuntimeException('Backup metadata could not be added to the archive.');
            }

            $this->encryptArchiveEntry($zip, 'backup-metadata.json');

            if (! $zip->close()) {
                throw new RuntimeException('The ZIP archive could not be finalized.');
            }
        } catch (Throwable $exception) {
            $zip->close();
            throw $exception;
        }

        return $fileCount;
    }

    private function addFileToArchive(ZipArchive $zip, string $source, string $entry): void
    {
        if (! $zip->addFile($source, $entry)) {
            throw new RuntimeException("File [{$source}] could not be added to the backup archive.");
        }

        $this->encryptArchiveEntry($zip, $entry);
    }

    private function encryptArchiveEntry(ZipArchive $zip, string $entry): void
    {
        $password = (string) config('backup.archive.password', '');

        if ($password === '') {
            return;
        }

        if (
            ! method_exists($zip, 'setEncryptionName')
            || ! defined(ZipArchive::class.'::EM_AES_256')
            || ! $zip->setEncryptionName($entry, ZipArchive::EM_AES_256, $password)
        ) {
            throw new RuntimeException('AES-256 ZIP encryption is unavailable in this PHP ZIP build.');
        }
    }

    private function applyRetention(FilesystemAdapter $disk, string $prefix, string $currentTarget): void
    {
        $days = max(0, (int) config('backup.retention.days', 30));
        $maximum = max(0, (int) config('backup.retention.maximum_backups', 30));
        $cutoff = now('UTC')->subDays($days)->getTimestamp();
        $archives = [];
        $archivePattern = '/^'.preg_quote($this->archiveNamePrefix(), '/').
            '-\d{8}T\d{6}Z-[a-z0-9]{8}\.zip$/i';

        foreach ($disk->files($prefix) as $path) {
            if (preg_match($archivePattern, basename($path)) !== 1) {
                continue;
            }

            $archives[] = [
                'path' => $path,
                'modified' => $disk->lastModified($path),
            ];
        }

        $toDelete = [];

        if ($days > 0) {
            foreach ($archives as $archive) {
                if ($archive['path'] !== $currentTarget && $archive['modified'] < $cutoff) {
                    $toDelete[] = $archive['path'];
                }
            }
        }

        $remaining = array_values(array_filter(
            $archives,
            fn (array $archive): bool => ! in_array($archive['path'], $toDelete, true),
        ));
        usort($remaining, function (array $left, array $right) use ($currentTarget): int {
            if ($left['path'] === $currentTarget) {
                return -1;
            }

            if ($right['path'] === $currentTarget) {
                return 1;
            }

            return $right['modified'] <=> $left['modified'];
        });

        if ($maximum > 0 && count($remaining) > $maximum) {
            foreach (array_slice($remaining, $maximum) as $archive) {
                $toDelete[] = $archive['path'];
            }
        }

        $toDelete = array_values(array_unique($toDelete));

        if ($toDelete !== [] && ! $disk->delete($toDelete)) {
            throw new RuntimeException('The backup was created, but expired backup archives could not be deleted.');
        }

        if (! $disk->exists($currentTarget)) {
            throw new RuntimeException('Retention completed, but the newly created backup archive is missing.');
        }
    }

    private function deleteIncompleteUpload(FilesystemAdapter $disk, string $target): void
    {
        try {
            if ($disk->exists($target)) {
                $disk->delete($target);
            }
        } catch (Throwable) {
            // Preserve the original upload error. Operations staff should also
            // inspect the destination for the uniquely named partial object.
        }
    }

    private function archiveNamePrefix(): string
    {
        $prefix = Str::slug((string) config('backup.archive.name_prefix', 'e-clearance'));

        return $prefix !== '' ? $prefix : 'e-clearance';
    }

    private function archiveRootName(string $path, int $index): string
    {
        $base = realpath(base_path()) ?: base_path();

        if ($this->pathIsWithin($path, $base)) {
            $relative = ltrim(substr($path, strlen($base)), '\\/');

            return 'files/'.trim(str_replace('\\', '/', $relative), '/');
        }

        $name = Str::slug(basename($path));

        return 'files/external-'.($index + 1).'-'.($name !== '' ? $name : 'path');
    }

    /**
     * @param  array<int, string>  $exclusions
     */
    private function isExcluded(string $path, array $exclusions): bool
    {
        foreach ($exclusions as $excluded) {
            if ($this->pathIsWithin($path, $excluded)) {
                return true;
            }
        }

        return false;
    }

    private function pathIsWithin(string $path, string $parent): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $parent = rtrim(str_replace('\\', '/', $parent), '/');

        if (PHP_OS_FAMILY === 'Windows') {
            $path = strtolower($path);
            $parent = strtolower($parent);
        }

        return $path === $parent || str_starts_with($path, $parent.'/');
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|\\\\\\\\|\/)/', $path) === 1;
    }

    private function validatedBackupPrefix(): string
    {
        $prefix = trim(str_replace('\\', '/', (string) config('backup.path', 'system-backups')), '/');

        if ($prefix === '' || in_array('..', explode('/', $prefix), true)) {
            throw new RuntimeException('The configured backup path is empty or unsafe.');
        }

        return $prefix;
    }

    private function validatedTemporaryRoot(): string
    {
        $path = trim((string) config('backup.temporary_path'));

        if ($path === '' || ! $this->isAbsolutePath($path)) {
            throw new RuntimeException('The backup temporary path must be an absolute path.');
        }

        $path = rtrim($path, '\\/');

        if ($path === '' || preg_match('/^[A-Za-z]:$/', $path) === 1) {
            throw new RuntimeException('A filesystem root cannot be used as the backup temporary path.');
        }

        return $path;
    }

    private function optionString(string $name): string
    {
        $value = $this->option($name);

        return is_string($value) ? trim($value) : '';
    }

    private function assertZipSupport(): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP ZIP extension is required to create system backups.');
        }
    }
}
