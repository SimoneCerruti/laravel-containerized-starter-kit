<?php

declare(strict_types=1);

use App\Actions\CreateUser;
use App\Enums\Role;

it('makes the created user a super admin', function (): void {
    $user = CreateUser::run('Ada', 'Lovelace', 'ada@example.com', 'a-long-password');

    expect($user->hasRole(Role::SUPER_ADMIN))->toBeTrue()
        ->and($user->hasRole(Role::USER))->toBeFalse();
});
