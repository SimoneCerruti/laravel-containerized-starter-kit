<?php

declare(strict_types=1);

/*
 * Database and file backups with a daily run, cleanup and health monitoring.
 */
return [
    'require' => [
        'spatie/laravel-backup' => '^10.3',
    ],
    'env' => [
        'BACKUP_ARCHIVE_PASSWORD' => '',
        'BACKUP_NOTIFICATION_EMAIL' => '',
    ],
    'fragments' => [
        'routes/console.php' => [
            'schedule' => "Schedule::command('backup:clean')->dailyAt('01:00');\nSchedule::command('backup:run')->dailyAt('01:30');\nSchedule::command('backup:monitor')->dailyAt('03:00');",
        ],
    ],
    'artisan' => [
        'php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider" --tag=backup-config --no-interaction',
    ],
];
