<?php

declare(strict_types=1);

/*
 * Users created by an admin must choose their own password: `password_changed_at` stays null until
 * they do, and `EnsurePasswordIsChanged` redirects them to the profile page meanwhile.
 * The factory creates users with the password already changed; `withInitialPassword()` resets it.
 */
return [
    'fragments' => [
        'database/migrations/0001_01_01_000000_create_users_table.php' => [
            'users-columns' => '$table->timestamp(\'password_changed_at\')->nullable();',
        ],
        'app/Models/User.php' => [
            'user-phpdoc' => '@property CarbonImmutable|null $password_changed_at',
            'user-casts' => '\'password_changed_at\' => \'immutable_datetime\',',
        ],
        'database/factories/UserFactory.php' => [
            'factory-definition' => '\'password_changed_at\' => now(),',
            'factory-states' => "public function withInitialPassword(): static {\n    return \$this->state(fn (array \$attributes): array => [\n        'password_changed_at' => null,\n    ]);\n}",
        ],
        'tests/ArchTest.php' => [
            'arch-strict-ignores' => "'App\\Filament\\Pages\\EditProfile',",
        ],
        'app/Providers/Filament/__KIT_PANEL_CLASS__PanelProvider.php' => [
            'panel-imports' => "use App\\Filament\\Pages\\EditProfile;\nuse App\\Http\\Middleware\\EnsurePasswordIsChanged;",
            'panel-auth-middleware' => 'EnsurePasswordIsChanged::class,',
            'panel-hooks' => '->profile(EditProfile::class)',
        ],
    ],
];
