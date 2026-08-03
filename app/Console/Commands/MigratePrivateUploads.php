<?php

namespace App\Console\Commands;

use App\Support\SecureUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigratePrivateUploads extends Command
{
    protected $signature = 'system:migrate-private-uploads {--dry-run : Report files without moving them}';

    protected $description = 'Move legacy clearance submissions from the public disk to private storage';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');
        $paths = collect(['student_submissions', 'office_submissions'])
            ->flatMap(fn (string $directory) => $public->allFiles($directory))
            ->unique()
            ->values();

        if ($paths->isEmpty()) {
            $this->info('No legacy public submission files were found.');

            return self::SUCCESS;
        }

        $moved = 0;
        $skipped = 0;

        foreach ($paths as $path) {
            if (! SecureUpload::isSafeRelativePath($path)) {
                $this->error("Skipped unsafe relative path: {$path}");
                $skipped++;

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("Would move: {$path}");

                continue;
            }

            try {
                if ($private->exists($path)) {
                    if ($private->size($path) !== $public->size($path)) {
                        $this->error("Skipped conflicting private file: {$path}");
                        $skipped++;

                        continue;
                    }
                } else {
                    $stream = $public->readStream($path);
                    if (! is_resource($stream)) {
                        throw new \RuntimeException('The source file could not be opened.');
                    }

                    try {
                        $written = $private->writeStream($path, $stream);
                    } finally {
                        fclose($stream);
                    }

                    if (! $written || ! $private->exists($path)
                        || $private->size($path) !== $public->size($path)) {
                        $private->delete($path);
                        throw new \RuntimeException('The private copy could not be verified.');
                    }
                }

                if (! $public->delete($path)) {
                    throw new \RuntimeException('The verified public copy could not be removed.');
                }

                $this->info("Moved: {$path}");
                $moved++;
            } catch (Throwable $exception) {
                $this->error("Failed: {$path} ({$exception->getMessage()})");
                $skipped++;
            }
        }

        if ($this->option('dry-run')) {
            $this->info("Dry run complete: {$paths->count()} file(s) found.");

            return self::SUCCESS;
        }

        $this->info("Migration complete: {$moved} moved, {$skipped} skipped.");

        return $skipped === 0 ? self::SUCCESS : self::FAILURE;
    }
}
