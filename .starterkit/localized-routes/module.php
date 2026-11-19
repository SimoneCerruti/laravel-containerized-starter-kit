<?php

declare(strict_types=1);

/*
 * Locale-prefixed public URLs (`/it/...`) with mcamara/laravel-localization. The public routes live
 * in the localized group of `routes/web.php` (the welcome route is named `home`); the panel and the
 * routes registered at the `web-routes` marker (e.g. the OAuth callbacks) are never localized.
 * `config/laravellocalization.php` builds the supported locales from the `Locale` enum, and
 * `tests/Routes/LocalizedRoutesTest.php` checks every named public route for every locale.
 */
return [
    'require' => [
        'mcamara/laravel-localization' => '^2.4',
    ],
    'replace' => [
        'routes/web.php' => [
            "use Illuminate\\Support\\Facades\\Route;\n" => "use Illuminate\\Support\\Facades\\Route;\nuse Mcamara\\LaravelLocalization\\Facades\\LaravelLocalization;\n",
            "Route::get('/', function () {\n    return view('welcome');\n});" => <<<'PHP'
Route::prefix(LaravelLocalization::setLocale())
    ->middleware(['localeSessionRedirect', 'localizationRedirect', 'localize'])
    ->group(function (): void {
        Route::get('/', function () {
            return view('welcome');
        })->name('home');
    });
PHP,
        ],
    ],
    'fragments' => [
        'bootstrap/app.php' => [
            'bootstrap-imports' => "use Mcamara\\LaravelLocalization\\Middleware\\LaravelLocalizationRedirectFilter;\nuse Mcamara\\LaravelLocalization\\Middleware\\LaravelLocalizationRoutes;\nuse Mcamara\\LaravelLocalization\\Middleware\\LocaleSessionRedirect;",
            'middleware' => "\$middleware->alias([\n    'localize' => LaravelLocalizationRoutes::class,\n    'localizationRedirect' => LaravelLocalizationRedirectFilter::class,\n    'localeSessionRedirect' => LocaleSessionRedirect::class,\n]);",
        ],
        'tests/Pest.php' => [
            'pest-imports' => "use Illuminate\\Routing\\Route as LocalizedRoute;\nuse Illuminate\\Support\\Facades\\Route;",
            'pest-helpers' => 'fragments/pest-helpers.php',
        ],
    ],
];
