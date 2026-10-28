<?php

declare(strict_types=1);

use App\Actions\CreateUser;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;

it('creates a user with a verified email and a hashed password', function (): void {
    expect(User::query()->count())->toBe(0);

    $user = CreateUser::run('Ada', 'Lovelace', 'ada@example.com', 'a-long-password');

    expect(User::query()->count())->toBe(1)
        ->and($user->fresh())
        ->first_name->toBe('Ada')
        ->last_name->toBe('Lovelace')
        ->email->toBe('ada@example.com')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('a-long-password', $user->password))->toBeTrue();
});

it('does not persist anything when the user cannot be created', function (): void {
    User::factory()->create(['email' => 'ada@example.com']);

    expect(fn () => CreateUser::run('Ada', 'Lovelace', 'ada@example.com', 'a-long-password'))
        ->toThrow(UniqueConstraintViolationException::class)
        ->and(User::query()->count())->toBe(1);
});
