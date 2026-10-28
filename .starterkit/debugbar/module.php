<?php

declare(strict_types=1);

/*
 * Laravel Debugbar, a dev-only package (`require-dev`, never in the production image). It is not part
 * of the base project: it is installed only when selected, together with the ignore rules for its
 * storage folder.
 */
return [
    'require-dev' => [
        'fruitcake/laravel-debugbar' => '^4.4',
    ],
    'fragments' => [
        '.gitignore' => [
            'gitignore' => '/storage/debugbar',
        ],
        '.dockerignore' => [
            'dockerignore' => '/storage/debugbar',
        ],
    ],
];
