<?php

declare(strict_types=1);

/*
 * Always installed. The base packages, the `app/` skeleton and the quality tooling already ship
 * in the project, so the module only adds its own files (GitHub workflows, issue templates,
 * the project README) and the env values that come from the answers. The base `.env.example`
 * keeps valid defaults for them, so Composer and Artisan work before the kit is installed.
 */
return [
    'env' => [
        'APP_NAME' => '__KIT_APP_NAME__',
        'APP_URL' => 'http://localhost:__KIT_APP_PORT__',
        'MAIL_FROM_ADDRESS' => 'test@__KIT_APP_SLUG__.test',
    ],
];
