<?php

declare(strict_types=1);

/*
 * Typed application settings stored in the database (spatie/laravel-settings).
 * With the panel, the Filament plugin lets settings classes back a settings page.
 */
return [
    'require' => [
        'spatie/laravel-settings' => '^3.9',
    ],
    'artisan' => [
        'php artisan vendor:publish --provider="Spatie\LaravelSettings\LaravelSettingsServiceProvider" --tag=migrations --tag=settings --no-interaction',
    ],
    'fragments' => [
        'tests/ArchTest.php' => [
            'arch-vendor-migrations' => "'create_settings_table',",
        ],
    ],
    'variants' => [
        'filament' => [
            'require' => ['filament/spatie-laravel-settings-plugin' => '^5.0'],
        ],
    ],
];
