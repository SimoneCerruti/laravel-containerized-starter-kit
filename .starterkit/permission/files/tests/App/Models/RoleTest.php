<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Permission;
use App\Models\Role as RoleModel;
use App\Models\User;

it('uses uuid keys for roles and permissions', function (): void {
    expect(str(RoleModel::factory()->create()->getKey())->isUuid())->toBeTrue()
        ->and(str(Permission::factory()->create()->getKey())->isUuid())->toBeTrue();
});

it('gives a user the role chosen in the factory state', function (): void {
    $user = User::factory()->withRole(Role::ADMIN)->create();

    expect($user->hasRole(Role::ADMIN))->toBeTrue()
        ->and($user->hasRole(Role::USER))->toBeFalse();
});
