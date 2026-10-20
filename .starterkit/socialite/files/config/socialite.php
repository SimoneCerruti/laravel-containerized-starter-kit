<?php

declare(strict_types=1);

return [
    /*
     * Providers that may start a sign-in. Each one needs a matching entry in config/services.php.
     */
    'providers' => array_values(array_filter(explode(',', (string) env('SOCIALITE_PROVIDERS', '')))),

    /*
     * When false, only users that already exist can sign in with a provider.
     */
    'registration' => (bool) env('SOCIALITE_REGISTRATION', false),

    /*
     * Providers whose email claim is not reliably verified (e.g. a multi-tenant Microsoft app) are
     * rejected unless listed here. Only list a provider whose tenant or directory you control.
     */
    'trusted_providers' => array_values(array_filter(explode(',', (string) env('SOCIALITE_TRUSTED_PROVIDERS', '')))),

    /*
     * Persist the provider's access and refresh tokens (encrypted). Needed only to call the
     * provider's API after sign-in.
     */
    'store_tokens' => (bool) env('SOCIALITE_STORE_TOKENS', false),

    'login_url' => (string) env('SOCIALITE_LOGIN_URL', '/login'),

    'home_url' => (string) env('SOCIALITE_HOME_URL', '/'),
];
