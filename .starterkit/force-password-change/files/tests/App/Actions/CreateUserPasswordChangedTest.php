<?php

declare(strict_types=1);

use App\Actions\CreateUser;

it('marks the password of the created user as already changed', function (): void {
    $user = CreateUser::run('Ada', 'Lovelace', 'ada@example.com', 'Str0ng-Passw0rd!');

    expect($user->fresh()->password_changed_at)->not->toBeNull()
        ->and($user->fresh()->password_changed_at->isToday())->toBeTrue();
});
