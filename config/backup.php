<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup System Status
    |--------------------------------------------------------------------------
    |
    | Master switch to enable or disable automatic and manual backups.
    |
    */

    'enabled' => env('BACKUP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Backup Storage Disks
    |--------------------------------------------------------------------------
    |
    | Primary storage disk for backup archives. By default, backups are stored
    | on the private local filesystem disk. An optional remote disk (such as S3)
    | can be specified to automatically replicate backups off-site.
    |
    */

    'disk' => env('BACKUP_DISK', 'local'),

    'path' => env('BACKUP_PATH', 'backups'),

    'remote_disk' => env('BACKUP_REMOTE_DISK', null),

    /*
    |--------------------------------------------------------------------------
    | Scheduled Execution Time
    |--------------------------------------------------------------------------
    |
    | The time (in 24-hour HH:MM format) when daily automatic backups execute.
    | Runs every night at 11:30 PM (23:30) in the application timezone.
    |
    */

    'scheduled_time' => env('BACKUP_SCHEDULED_TIME', '23:30'),

    /*
    |--------------------------------------------------------------------------
    | Persistent Files Inclusion
    |--------------------------------------------------------------------------
    |
    | Whether to bundle persistent uploaded files (meter readings, payment
    | slips, maintenance photos) inside the backup archive alongside the database.
    |
    */

    'include_files' => env('BACKUP_INCLUDE_FILES', true),

    'files_directories' => [
        'public', // storage/app/public
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Archive Encryption
    |--------------------------------------------------------------------------
    |
    | When enabled, backup archives are encrypted at rest using AES-256-CBC
    | before writing to storage. Encryption keys are decoupled from the archive.
    |
    */

    'encryption_enabled' => env('BACKUP_ENCRYPTION_ENABLED', false),

    'encryption_key' => env('BACKUP_ENCRYPTION_KEY', env('APP_KEY')),

    /*
    |--------------------------------------------------------------------------
    | Backup Retention Policy
    |--------------------------------------------------------------------------
    |
    | Configurable retention rules to prune older backups while safeguarding:
    | - The latest successful backup (NEVER deleted).
    | - Protected backups (is_protected = true).
    | - A minimum number of retained backups.
    |
    */

    'retention' => [
        'daily' => (int) env('BACKUP_DAILY_RETENTION', 30),
        'weekly' => (int) env('BACKUP_WEEKLY_RETENTION', 12),
        'monthly' => (int) env('BACKUP_MONTHLY_RETENTION', 12),
        'keep_minimum' => (int) env('BACKUP_KEEP_MINIMUM', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Dump & Restore Configuration
    |--------------------------------------------------------------------------
    |
    | PostgreSQL configuration for native pg_dump and psql tools.
    | Automatically falls back to docker container execution if host version mismatches.
    |
    */

    'database' => [
        'pg_dump_path' => env('BACKUP_PG_DUMP_PATH', null),
        'psql_path' => env('BACKUP_PSQL_PATH', null),
        'docker_container' => env('BACKUP_DOCKER_CONTAINER', 'uas-postgres'),
        'timeout' => (int) env('BACKUP_TIMEOUT', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Notification preferences on backup completion or failure.
    |
    */

    'notifications' => [
        'notify_on_success' => env('BACKUP_NOTIFY_ON_SUCCESS', true),
        'notify_on_failure' => env('BACKUP_NOTIFY_ON_FAILURE', true),
        'mail_to' => env('BACKUP_NOTIFICATION_EMAIL', null),
    ],

];
