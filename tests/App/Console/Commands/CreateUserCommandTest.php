<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\artisan;

it('creates a verified user from the answers', function (): void {
    expect(User::query()->count())->toBe(0);

    artisan('users:create')
        ->expectsQuestion('First name', 'Ada')
        ->expectsQuestion('Last name', 'Lovelace')
        ->expectsQuestion('Email', 'ada@example.com')
        ->expectsQuestion('Password', 'a-long-password')
        ->expectsOutputToContain('User ada@example.com created.')
        ->assertSuccessful();

    $user = User::query()->where('email', 'ada@example.com')->firstOrFail();

    expect($user->hasVerifiedEmail())->toBeTrue();
});

it('takes the name and the email from the options', function (): void {
    artisan('users:create', ['--first-name' => 'Ada', '--last-name' => 'Lovelace', '--email' => 'ada@example.com'])
        ->expectsQuestion('Password', 'a-long-password')
        ->assertSuccessful();

    expect(User::query()->where('email', 'ada@example.com')->exists())->toBeTrue();
});

it('fails when a user with the same email already exists', function (): void {
    User::factory()->create(['email' => 'ada@example.com']);

    artisan('users:create', ['--first-name' => 'Ada', '--last-name' => 'Lovelace', '--email' => 'ada@example.com'])
        ->expectsOutputToContain('A user with the email ada@example.com already exists.')
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
});

it('creates the user without prompts when every option is given', function (): void {
    artisan('users:create', ['--first-name' => '0', '--last-name' => 'Lovelace', '--email' => 'ada@example.com', '--password' => 'a-long-password'])
        ->assertSuccessful();

    expect(User::query()->where('email', 'ada@example.com')->firstOrFail()->first_name)->toBe('0');
});

it('validates the email and the password given as options', function (string $option, string $value, string $message): void {
    artisan('users:create', ['--first-name' => 'Ada', '--last-name' => 'Lovelace', '--email' => 'ada@example.com', '--password' => 'a-long-password', $option => $value])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
})->with([
    'invalid email' => ['--email', 'not-an-email', 'email'],
    'short password' => ['--password', 'short', 'at least 12'],
]);

it('treats the email as case insensitive when looking for a duplicate', function (): void {
    User::factory()->create(['email' => 'ada@example.com']);

    artisan('users:create', ['--first-name' => 'Ada', '--last-name' => 'Lovelace', '--email' => 'Ada@Example.com', '--password' => 'a-long-password'])
        ->expectsOutputToContain('already exists')
        ->assertFailed();
});
