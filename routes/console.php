<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (config('backup.enabled')) {
    $backupSchedule = Schedule::command('backup:system')
        ->dailyAt((string) config('backup.schedule.time', '02:00'))
        ->timezone((string) config('backup.schedule.timezone', 'UTC'))
        ->withoutOverlapping(max(
            1,
            (int) config('backup.schedule.without_overlapping_minutes', 180),
        ))
        ->onFailure(fn () => Log::critical(
            'The scheduled system backup failed. Inspect the preceding backup error and verify the offsite destination.',
        ));

    if (config('backup.schedule.on_one_server')) {
        $backupSchedule->onOneServer();
    }
}
