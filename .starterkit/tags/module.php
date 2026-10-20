<?php

declare(strict_types=1);

/*
 * Tags for Eloquent models (spatie/laravel-tags), with the Filament form and column
 * components when the panel is installed.
 */
return [
    'require' => [
        'spatie/laravel-tags' => '^4.12',
    ],
    'artisan' => [
        'php artisan vendor:publish --provider="Spatie\Tags\TagsServiceProvider" --tag=tags-migrations --no-interaction',
    ],
    'fragments' => [
        'tests/ArchTest.php' => [
            'arch-vendor-migrations' => "'create_tag_tables',",
        ],
    ],
    'variants' => [
        'filament' => [
            'require' => ['filament/spatie-laravel-tags-plugin' => '^5.0'],
        ],
    ],
];
