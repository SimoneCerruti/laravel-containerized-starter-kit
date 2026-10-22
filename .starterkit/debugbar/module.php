<?php

declare(strict_types=1);

/*
 * Laravel Debugbar, a dev-only package (`require-dev`, never in the production image). It is not part
 * of the base project: it is installed only when selected. Its storage folder is already ignored by
 * `.gitignore` and `.dockerignore`.
 */
return [
    'require-dev' => [
        'fruitcake/laravel-debugbar' => '^4.4',
    ],
];
