<?php

declare(strict_types=1);

/*
 * Public sitemap, regenerated every night.
 */
return [
    'require' => [
        'spatie/laravel-sitemap' => '^8.2',
    ],
    'fragments' => [
        'routes/console.php' => [
            'schedule' => "Schedule::command('sitemap:generate')->dailyAt('02:00');",
        ],
    ],
];
