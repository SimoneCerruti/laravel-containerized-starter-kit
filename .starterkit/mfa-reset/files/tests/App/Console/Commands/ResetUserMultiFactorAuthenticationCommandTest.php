<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\artisan;

it('resets the multi-factor methods of the user with the given email', function (): void {
    $user = User::factory()->withEmailAuthentication()->create();

    artisan('users:reset-mfa', ['email' => $user->email])->assertSuccessful();

    expect($user->fresh()->has_email_authentication)->toBeFalse();
});

it('fails when the user does not exist', function (): void {
    artisan('users:reset-mfa', ['email' => 'missing@example.com'])->assertFailed();
});
