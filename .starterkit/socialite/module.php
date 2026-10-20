<?php

declare(strict_types=1);

/*
 * Sign-in with OAuth providers. `variants` are keyed by the providers picked in the install
 * question and merged on top of the base manifest: Google and GitHub ship with Socialite,
 * Microsoft and Apple need a SocialiteProviders package and an event listener, `other` only
 * leaves the listener marker in App\Providers\SocialiteServiceProvider.
 * The Filament login buttons are rendered by `components.socialite-buttons` through a
 * `PanelsRenderHook::AUTH_LOGIN_FORM_AFTER` hook added by the filament panel provider.
 */
return [
    'require' => [
        'laravel/socialite' => '^5.31',
        'socialiteproviders/manager' => '^4.8',
    ],
    'env' => [
        'SOCIALITE_PROVIDERS' => '__KIT_SOCIALITE_PROVIDERS__',
        'SOCIALITE_REGISTRATION' => '__KIT_SOCIALITE_REGISTRATION__',
        'SOCIALITE_TRUSTED_PROVIDERS' => '',
        'SOCIALITE_STORE_TOKENS' => 'false',
        'SOCIALITE_LOGIN_URL' => '__KIT_SOCIALITE_LOGIN_URL__',
        'SOCIALITE_HOME_URL' => '__KIT_SOCIALITE_HOME_URL__',
    ],
    'fragments' => [
        'bootstrap/providers.php' => [
            'providers-imports' => 'use App\Providers\SocialiteServiceProvider;',
            'providers' => 'SocialiteServiceProvider::class,',
        ],
        'routes/web.php' => [
            'web-routes' => 'fragments/web-routes.php',
        ],
        'app/Models/User.php' => [
            'user-imports' => "use Illuminate\\Database\\Eloquent\\Collection;\nuse Illuminate\\Database\\Eloquent\\Relations\\HasMany;",
            'user-phpdoc' => '@property-read Collection<int, SocialAccount> $socialAccounts',
            'user-methods' => 'fragments/user-methods.php',
        ],
    ],
    'variants' => [
        'google' => [
            'env' => ['GOOGLE_CLIENT_ID' => '', 'GOOGLE_CLIENT_SECRET' => ''],
            'fragments' => [
                'config/services.php' => [
                    'services' => "'google' => [\n    'client_id' => env('GOOGLE_CLIENT_ID'),\n    'client_secret' => env('GOOGLE_CLIENT_SECRET'),\n    'redirect' => '/auth/google/callback',\n],",
                ],
            ],
        ],
        'github' => [
            'env' => ['GITHUB_CLIENT_ID' => '', 'GITHUB_CLIENT_SECRET' => ''],
            'fragments' => [
                'config/services.php' => [
                    'services' => "'github' => [\n    'client_id' => env('GITHUB_CLIENT_ID'),\n    'client_secret' => env('GITHUB_CLIENT_SECRET'),\n    'redirect' => '/auth/github/callback',\n],",
                ],
            ],
        ],
        'microsoft' => [
            'require' => ['socialiteproviders/microsoft' => '^4.3'],
            'env' => ['MICROSOFT_CLIENT_ID' => '', 'MICROSOFT_CLIENT_SECRET' => '', 'MICROSOFT_TENANT' => 'common'],
            'fragments' => [
                'config/services.php' => [
                    'services' => "'microsoft' => [\n    'client_id' => env('MICROSOFT_CLIENT_ID'),\n    'client_secret' => env('MICROSOFT_CLIENT_SECRET'),\n    'redirect' => '/auth/microsoft/callback',\n    'tenant' => env('MICROSOFT_TENANT', 'common'),\n],",
                ],
                'app/Providers/SocialiteServiceProvider.php' => [
                    'socialite-listeners' => "\$event->extendSocialite('microsoft', \\SocialiteProviders\\Microsoft\\Provider::class);",
                ],
            ],
        ],
        'apple' => [
            'require' => ['socialiteproviders/apple' => '^5.7'],
            'env' => ['APPLE_CLIENT_ID' => '', 'APPLE_CLIENT_SECRET' => ''],
            'fragments' => [
                'config/services.php' => [
                    'services' => "'apple' => [\n    'client_id' => env('APPLE_CLIENT_ID'),\n    'client_secret' => env('APPLE_CLIENT_SECRET'),\n    'redirect' => '/auth/apple/callback',\n],",
                ],
                'app/Providers/SocialiteServiceProvider.php' => [
                    'socialite-listeners' => "\$event->extendSocialite('apple', \\SocialiteProviders\\Apple\\Provider::class);",
                ],
            ],
        ],
        'other' => [],
    ],
];
