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

it('finds the user whatever the case of the email', function (): void {
    $user = User::factory()->withEmailAuthentication()->create(['email' => 'ada@example.com']);

    artisan('users:reset-mfa', ['email' => 'ADA@Example.COM'])->assertSuccessful();

    expect($user->fresh()->has_email_authentication)->toBeFalse();
});
