<?php

declare(strict_types=1);

/*
 * Database and file backups with a daily run, cleanup and health monitoring. `config/backup.php`
 * ships with the module and reads the disks from BACKUP_DISKS (comma separated). The `google-drive`
 * variant adds a Google Drive disk, registered in AppServiceProvider, next to the local one.
 */
return [
    'require' => [
        'spatie/laravel-backup' => '^10.3',
    ],
    'env' => [
        'BACKUP_DISKS' => 'local',
        'BACKUP_ARCHIVE_PASSWORD' => '',
        'BACKUP_NOTIFICATION_EMAIL' => '',
    ],
    'fragments' => [
        'routes/console.php' => [
            'schedule' => "Schedule::command('backup:clean')->dailyAt('01:00');\nSchedule::command('backup:run')->dailyAt('01:30');\nSchedule::command('backup:monitor')->dailyAt('03:00');",
        ],
    ],
    'variants' => [
        'google-drive' => [
            'require' => [
                'masbug/flysystem-google-drive-ext' => '^2.4',
            ],
            'env' => [
                'BACKUP_DISKS' => 'local,google',
                'GOOGLE_DRIVE_CLIENT_ID' => '',
                'GOOGLE_DRIVE_CLIENT_SECRET' => '',
                'GOOGLE_DRIVE_REFRESH_TOKEN' => '',
                'GOOGLE_DRIVE_FOLDER' => 'Backups/__KIT_APP_SLUG__',
            ],
            'fragments' => [
                'config/filesystems.php' => [
                    'filesystem-disks' => 'fragments/google-disk.php',
                ],
                'app/Providers/AppServiceProvider.php' => [
                    'app-imports' => 'fragments/google-drive-imports.php',
                    'app-boot' => 'fragments/google-drive-boot.php',
                ],
            ],
            'json' => [
                'composer' => [
                    'extra.google/apiclient-services' => ['Drive'],
                    'scripts.pre-autoload-dump' => ['Google\\Task\\Composer::cleanup'],
                ],
            ],
        ],
    ],
];
