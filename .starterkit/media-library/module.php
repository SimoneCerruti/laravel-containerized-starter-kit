<?php

declare(strict_types=1);

/*
 * Spatie media library with a path generator that groups files by owner model. The Filament
 * plugin is added only with the panel.
 */
return [
    'require' => [
        'spatie/laravel-medialibrary' => '^11.0',
    ],
    'env' => [
        'MEDIA_DISK' => 'local',
    ],
    'fragments' => [
        'tests/ArchTest.php' => [
            'arch-strict-ignores' => "'App\\Support\\MediaPathGenerator',",
        ],
    ],
    'variants' => [
        'filament' => [
            'require' => ['filament/spatie-laravel-media-library-plugin' => '^5.0'],
        ],
    ],
];
