<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;

/*
 * Overrides of the spatie/laravel-permission defaults: UUID models and a UUID morph key.
 */
return [
    'models' => [
        'permission' => Permission::class,
        'role' => Role::class,
    ],

    'column_names' => [
        'model_morph_key' => 'model_id',
    ],
];
