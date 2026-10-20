<?php

declare(strict_types=1);

/*
 * Restricts panel access to a list of email domains (`ALLOWED_ADMIN_EMAIL_DOMAINS`, comma separated).
 */
return [
    'env' => [
        'ALLOWED_ADMIN_EMAIL_DOMAINS' => '__KIT_ALLOWED_EMAIL_DOMAINS__',
    ],
    'fragments' => [
        'config/app.php' => [
            'app-config' => 'fragments/config-app.php',
        ],
        'app/Models/User.php' => [
            'user-imports' => 'use App\Actions\CheckAllowedEmailForAdminPanel;',
            'user-panel-access' => 'CheckAllowedEmailForAdminPanel::run($this->email),',
        ],
    ],
];
