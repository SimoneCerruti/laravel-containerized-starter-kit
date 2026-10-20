<?php

declare(strict_types=1);

use App\Actions\ResetUserMultiFactorAuthentication;
use App\Models\User;

it('removes every multi-factor method from the user', function (): void {
    $user = User::factory()->withEmailAuthentication()->create(['app_authentication_secret' => 'SECRET', 'app_authentication_recovery_codes' => ['code']]);

    ResetUserMultiFactorAuthentication::run($user);

    expect($user->fresh())
        ->app_authentication_secret->toBeNull()
        ->app_authentication_recovery_codes->toBeNull()
        ->has_email_authentication->toBeFalse();
});
