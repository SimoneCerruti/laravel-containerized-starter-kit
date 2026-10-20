<?php

declare(strict_types=1);

/*
 * Slack notifications and an optional Slack log channel.
 */
return [
    'require' => [
        'laravel/slack-notification-channel' => '^3.10',
    ],
    'env' => [
        'SLACK_BOT_USER_OAUTH_TOKEN' => '',
        'SLACK_BOT_USER_DEFAULT_CHANNEL' => '',
        'LOG_SLACK_WEBHOOK_URL' => '',
    ],
];
