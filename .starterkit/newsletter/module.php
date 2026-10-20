<?php

declare(strict_types=1);

/*
 * Newsletter subscriptions (spatie/laravel-newsletter, Mailcoach/Mailchimp driver).
 */
return [
    'require' => [
        'spatie/laravel-newsletter' => '^5.4',
    ],
    'env' => [
        'MAILCHIMP_API_KEY' => '',
        'MAILCHIMP_LIST_ID' => '',
    ],
];
