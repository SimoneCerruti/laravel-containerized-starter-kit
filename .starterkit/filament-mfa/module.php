<?php

declare(strict_types=1);

/*
 * Filament multi-factor authentication: authenticator app (with recovery codes) and email codes.
 * `__KIT_MFA_REQUIRED__` is `true` when the install answer was "required".
 * NOTE: contract and class names follow Filament 5's MFA docs and must be checked against the
 * installed version (`search-docs`) when this module is first run.
 */
return [
    'fragments' => [
        'database/migrations/0001_01_01_000000_create_users_table.php' => [
            'users-columns' => "\$table->text('app_authentication_secret')->nullable();\n\$table->text('app_authentication_recovery_codes')->nullable();\n\$table->boolean('has_email_authentication')->default(false);",
        ],
        'app/Models/User.php' => [
            'user-imports' => "use Filament\\Auth\\MultiFactor\\App\\Contracts\\HasAppAuthentication;\nuse Filament\\Auth\\MultiFactor\\App\\Contracts\\HasAppAuthenticationRecovery;\nuse Filament\\Auth\\MultiFactor\\Email\\Contracts\\HasEmailAuthentication;",
            'user-phpdoc' => "@property string|null \$app_authentication_secret\n@property list<string>|null \$app_authentication_recovery_codes\n@property bool \$has_email_authentication",
            'user-hidden' => "'app_authentication_secret',\n'app_authentication_recovery_codes',",
            'user-implements' => 'HasAppAuthentication, HasAppAuthenticationRecovery, HasEmailAuthentication,',
            'user-non-loggable' => "'app_authentication_secret',\n'app_authentication_recovery_codes',",
            'user-attribute-defaults' => "'has_email_authentication' => false,",
            'user-casts' => "'app_authentication_secret' => 'encrypted',\n'app_authentication_recovery_codes' => 'encrypted:array',\n'has_email_authentication' => 'boolean',",
            'user-methods' => 'fragments/user-methods.php',
        ],
        'database/factories/UserFactory.php' => [
            'factory-definition' => "'app_authentication_secret' => null,\n'app_authentication_recovery_codes' => null,",
            'factory-states' => "public function withoutMfa(): static {\n    return \$this->state(fn (array \$attributes): array => [\n        'app_authentication_secret' => null,\n        'app_authentication_recovery_codes' => null,\n        'has_email_authentication' => false,\n    ]);\n}\n\npublic function withEmailAuthentication(): static {\n    return \$this->state(fn (array \$attributes): array => [\n        'has_email_authentication' => true,\n    ]);\n}",
        ],
        'app/Providers/Filament/__KIT_PANEL_CLASS__PanelProvider.php' => [
            'panel-imports' => "use Filament\\Auth\\MultiFactor\\App\\AppAuthentication;\nuse Filament\\Auth\\MultiFactor\\Email\\EmailAuthentication;",
            'panel-hooks' => '->multiFactorAuthentication([AppAuthentication::make()->recoverable(), EmailAuthentication::make()], isRequired: __KIT_MFA_REQUIRED__)',
        ],
    ],
];
