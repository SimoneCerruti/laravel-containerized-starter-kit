<?php

declare(strict_types=1);

/*
 * Locale-prefixed public URLs (`/it/...`) with mcamara/laravel-localization. Public routes
 * go inside the localized group at `@kit:web-routes`; the panel is never localized.
 */
return [
    'require' => [
        'mcamara/laravel-localization' => '^2.4',
    ],
    'fragments' => [
        'bootstrap/app.php' => [
            'bootstrap-imports' => "use Mcamara\\LaravelLocalization\\Middleware\\LaravelLocalizationRedirectFilter;\nuse Mcamara\\LaravelLocalization\\Middleware\\LaravelLocalizationRoutes;\nuse Mcamara\\LaravelLocalization\\Middleware\\LocaleSessionRedirect;",
            'middleware' => "\$middleware->alias([\n    'localize' => LaravelLocalizationRoutes::class,\n    'localizationRedirect' => LaravelLocalizationRedirectFilter::class,\n    'localeSessionRedirect' => LocaleSessionRedirect::class,\n]);",
        ],
    ],
    'artisan' => [
        'php artisan vendor:publish --provider="Mcamara\LaravelLocalization\LaravelLocalizationServiceProvider" --no-interaction',
    ],
];
