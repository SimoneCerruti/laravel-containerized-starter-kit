<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\User;
use App\Policies\UserPolicy;

dataset('user abilities', [
    'viewAny' => ['viewAny', 'ViewAny:User', false],
    'view' => ['view', 'View:User', true],
    'create' => ['create', 'Create:User', false],
    'update' => ['update', 'Update:User', true],
    'delete' => ['delete', 'Delete:User', true],
]);

it('denies every ability by default', function (string $ability, string $permission, bool $needs_record): void {
    $user = User::factory()->create();
    $arguments = $needs_record ? [$user, User::factory()->create()] : [$user];

    expect(new UserPolicy()->{$ability}(...$arguments))->toBeFalse();
})->with('user abilities');

it('allows an ability only when the user holds the matching permission', function (string $ability, string $permission, bool $needs_record): void {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate($permission));
    $arguments = $needs_record ? [$user, User::factory()->create()] : [$user];

    expect(new UserPolicy()->{$ability}(...$arguments))->toBeTrue();
})->with('user abilities');
