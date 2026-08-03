<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup destination
    |--------------------------------------------------------------------------
    |
    | Production backups must use a filesystem disk whose data is stored
    | outside the web host. The local disk is useful only for development and
    | is deliberately rejected by the production security preflight command.
    |
    */

    'enabled' => env('BACKUP_ENABLED', false),

    'disk' => env('BACKUP_DISK', 'local'),

    'path' => env('BACKUP_PATH', 'system-backups'),

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */

    'database_connection' => env('BACKUP_DATABASE_CONNECTION', env('DB_CONNECTION')),

    'mysql' => [
        'dump_binary' => env('BACKUP_MYSQLDUMP_BINARY', 'mysqldump'),
        'timeout' => (int) env('BACKUP_MYSQLDUMP_TIMEOUT', 900),
        'no_tablespaces' => env('BACKUP_MYSQL_NO_TABLESPACES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Files and archive
    |--------------------------------------------------------------------------
    */

    'include' => array_values(array_filter([
        storage_path('app/private'),
        env('BACKUP_INCLUDE_PUBLIC_STORAGE', true) ? storage_path('app/public') : null,
    ])),

    'exclude' => [
        storage_path('framework/backup-temp'),
    ],

    'temporary_path' => storage_path('framework/backup-temp'),

    'archive' => [
        'name_prefix' => env('BACKUP_NAME_PREFIX', 'e-clearance'),
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention and schedule
    |--------------------------------------------------------------------------
    */

    'retention' => [
        'days' => (int) env('BACKUP_RETENTION_DAYS', 30),
        'maximum_backups' => (int) env('BACKUP_RETENTION_COUNT', 30),
    ],

    'schedule' => [
        'time' => env('BACKUP_SCHEDULE_TIME', '02:00'),
        'timezone' => env('BACKUP_SCHEDULE_TIMEZONE', 'UTC'),
        'without_overlapping_minutes' => (int) env('BACKUP_OVERLAP_TIMEOUT', 180),
        'on_one_server' => env('BACKUP_ON_ONE_SERVER', false),
    ],

];
