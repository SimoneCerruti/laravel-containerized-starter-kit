<?php

declare(strict_types=1);

/*
 * PDF generation with spatie/laravel-pdf. The default driver renders through a headless browser:
 * the Dockerfile needs Chromium and Node when the app produces PDFs.
 */
return [
    'require' => [
        'spatie/laravel-pdf' => '^2.14',
    ],
];
