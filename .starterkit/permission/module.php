<?php

declare(strict_types=1);

/*
 * Roles and permissions on spatie/laravel-permission with UUID models. The Pest helpers assume
 * the module's `Role` enum (USER, ADMIN, SUPER_ADMIN) and the `User::factory()->withRole()` state,
 * both shipped by this module. With the panel, only admins reach it (`filament` variant).
 */
return [
    'require' => [
        'spatie/laravel-permission' => '^8.3',
    ],
    'fragments' => [
        'app/Models/User.php' => [
            'user-imports' => 'use Spatie\Permission\Traits\HasRoles;',
            'user-traits' => 'use HasRoles;',
        ],
        'database/factories/UserFactory.php' => [
            'factory-states' => "public function withRole(\\App\\Enums\\Role \$role): static {\n    return \$this->afterCreating(fn (\\App\\Models\\User \$user) => \$user->assignRole(\\App\\Models\\Role::findOrCreate(\$role->value)));\n}",
        ],
        'app/Actions/CreateUser.php' => [
            'create-user' => '$user->assignRole(\App\Models\Role::findOrCreate(\App\Enums\Role::SUPER_ADMIN->value));',
        ],
        'tests/Pest.php' => [
            'pest-imports' => "use App\\Enums\\Role;\nuse App\\Models\\User;",
            'pest-helpers' => 'fragments/pest-helpers.php',
        ],
    ],
    'variants' => [
        'filament' => [
            'fragments' => [
                'app/Models/User.php' => [
                    'user-imports' => 'use App\Enums\Role;',
                    'user-panel-access' => '$this->hasAnyRole([Role::ADMIN, Role::SUPER_ADMIN]),',
                ],
            ],
        ],
    ],
];
