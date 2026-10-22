<?php

declare(strict_types=1);

/*
 * Laravel Debugbar, a dev-only package (`require-dev`, never in the production image). The base
 * project ships with it; when it is not selected `when_disabled` takes it out with its storage folder.
 */
return [
    'when_disabled' => [
        'replace' => [
            '.gitignore' => [
                "/storage/debugbar\n" => '',
            ],
            '.dockerignore' => [
                "/storage/debugbar\n" => '',
            ],
        ],
        'remove_packages' => [
            'fruitcake/laravel-debugbar',
        ],
    ],
];
