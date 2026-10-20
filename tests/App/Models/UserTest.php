<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\freezeTime;

describe('factory', function (): void {
    test('creates a persisted, verified user', function (): void {
        $user = User::factory()->create();

        assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
        ]);
        expect($user->hasVerifiedEmail())->toBeTrue();
    });

    test('the unverified state leaves the email unverified', function (): void {
        $user = User::factory()->unverified()->create();

        expect($user->email_verified_at)->toBeNull();
    });
});

describe('attributes', function (): void {
    describe('id', function (): void {
        test('is generated as a uuid7 on create', function (): void {
            $user = User::factory()->create();

            expect($user->id)->toBeUuid()
                ->and(Str::isUuid($user->id, 7))->toBeTrue();
        });

        test('is a non-incrementing string primary key', function (): void {
            $user = new User;

            expect($user->getKeyName())->toBe('id')
                ->and($user->getKeyType())->toBe('string')
                ->and($user->getIncrementing())->toBeFalse()
                ->and($user->uniqueIds())->toBe(['id']);
        });

        test('is distinct for every created model', function (): void {
            $users = User::factory()->count(2)->create();

            expect($users->first()->id)->not->toBe($users->last()->id);
        });

        test('route model binding rejects a non-uuid value before hitting the database', function (): void {
            expect(fn () => (new User)->resolveRouteBinding('not-a-uuid'))
                ->toThrow(ModelNotFoundException::class, 'No query results for model [App\Models\User] not-a-uuid');
        });
    });

    describe('email', function (): void {
        test('is unique', function (): void {
            User::factory()->create(['email' => 'ada@example.com']);

            expect(fn () => User::factory()->create(['email' => 'ada@example.com']))
                ->toThrow(UniqueConstraintViolationException::class, 'users_email_unique');
        });

        test('is a well-formed email address', function (): void {
            $user = User::factory()->create();

            expect($user->email)->toBeEmail();
        });

        test('is the address used for verification and mail routing', function (): void {
            $user = User::factory()->create();

            expect($user->getEmailForVerification())->toBe($user->email)
                ->and($user->routeNotificationFor('mail'))->toBe($user->email);
        });
    });

    describe('email_verified_at', function (): void {
        test('is cast to an immutable Carbon instance when present', function (): void {
            $user = User::factory()->create();

            expect($user->email_verified_at)->toBeInstanceOf(CarbonImmutable::class);
        });

        test('is nullable and null means the email is unverified', function (): void {
            $user = User::factory()->unverified()->create();

            expect($user->email_verified_at)->toBeNull()
                ->and($user->hasVerifiedEmail())->toBeFalse();
        });

        test('marks the email as verified', function (): void {
            $user = User::factory()->unverified()->create();

            expect($user->hasVerifiedEmail())->toBeFalse()
                ->and($user->markEmailAsVerified())->toBeTrue()
                ->and($user->hasVerifiedEmail())->toBeTrue()
                ->and($user->fresh()?->email_verified_at)->not->toBeNull();
        });
    });

    describe('password', function (): void {
        test('is hidden from serialization', function (): void {
            $user = User::factory()->create();

            expect($user->getHidden())->toContain('password')
                ->and($user->toArray())->not->toHaveKey('password')
                ->and($user->toJson())->not->toContain($user->password);
        });

        test('is cast to a hashed value on save', function (): void {
            $user = User::factory()->withPassword('sup3r-S3cret!')->create();

            expect($user->password)->not->toBe('sup3r-S3cret!')
                ->and(Hash::check('sup3r-S3cret!', $user->password))->toBeTrue();
        });

        test('does not re-hash an already hashed value', function (): void {
            $hash = Hash::make('already-hashed-S3cret!');

            $user = User::factory()->create(['password' => $hash]);

            expect($user->password)->toBe($hash);
        });

        test('rejects a hash produced with a different configuration', function (): void {
            $foreign_hash = Hash::driver('bcrypt')->make('x', ['rounds' => 12]);

            expect(fn () => User::factory()->make(['password' => $foreign_hash]))
                ->toThrow(RuntimeException::class, "Could not verify the hashed value's configuration.");
        });
    });

    describe('remember_token', function (): void {
        test('is hidden from serialization', function (): void {
            $user = User::factory()->create();

            expect($user->getHidden())->toContain('remember_token')
                ->and($user->toArray())->not->toHaveKey('remember_token');
        });

        test('hidden means excluded by default, not dropped from the model', function (): void {
            $user = User::factory()->create();

            expect($user->makeVisible('remember_token')->toArray())->toHaveKey('remember_token');
        });
    });

});

describe('mass-assignment', function (): void {
    test('exposes the declared allowlist', function (): void {
        expect((new User)->getFillable())
            ->toBe(['first_name', 'last_name', 'email', 'password']);
    });

    test('fills every allowed attribute', function (): void {
        $user = new User([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'password' => 'sup3r-S3cret!',
        ]);

        expect($user->first_name)->toBe('Ada')
            ->and($user->last_name)->toBe('Lovelace')
            ->and($user->email)->toBe('ada@example.com')
            ->and(Hash::check('sup3r-S3cret!', $user->password))->toBeTrue();
    });

    test('rejects a non-fillable column', function (): void {
        expect(fn () => new User(['email_verified_at' => now()]))
            ->toThrow(
                MassAssignmentException::class,
                'Add fillable property [email_verified_at] to allow mass assignment on [App\Models\User].',
            );
    });

    test('rejects the primary key too', function (): void {
        expect(fn () => new User(['id' => (string) Str::uuid()]))
            ->toThrow(MassAssignmentException::class, 'Add fillable property [id] to allow mass assignment on [App\Models\User].');
    });

    test('factories bypass the guard for non-fillable defaults', function (): void {
        $user = User::factory()->create();

        expect($user->email_verified_at)->not->toBeNull()
            ->and($user->remember_token)->not->toBeNull();
    });
});

describe('activity logging', function (): void {
    beforeEach(function (): void {
        freezeTime();
    });

    test('logs the creation', function (): void {
        $user = User::factory()->create();

        assertCountActivityLogsFor($user, 1);

        $created = getLastActivityLogsFor($user, 'created');
        assertActivityLogHasChanges($created);

        $changes = $created?->attribute_changes?->toArray() ?? [];

        expect($changes)->toHaveKey('attributes')->not->toHaveKey('old')
            ->and($changes['attributes'] ?? [])
            ->toHaveKeys(['id', 'first_name', 'last_name', 'email'])
            ->not->toHaveKeys(['password', 'remember_token']);
    });

    test('logs only the dirty attributes on update', function (): void {
        $user = User::factory()->create();
        $original_first_name = $user->first_name;

        assertCountActivityLogsFor($user, 1);

        $user->update(['first_name' => 'Grace']);

        assertCountActivityLogsFor($user, 2);

        $updated = getLastActivityLogsFor($user, 'updated');
        assertActivityLogHasChanges($updated);
        expect($updated?->attribute_changes?->toArray())->toBe([
            'attributes' => ['first_name' => 'Grace'],
            'old' => ['first_name' => $original_first_name],
        ]);
    });

    test('never logs password or remember token changes', function (): void {
        $user = User::factory()->create();

        assertCountActivityLogsFor($user, 1);

        $user->update(['first_name' => 'Grace', 'password' => 'another-S3cret!']);

        assertCountActivityLogsFor($user, 2);

        $updated = getLastActivityLogsFor($user, 'updated');
        assertActivityLogHasChanges($updated);
        expect($updated?->attribute_changes?->get('attributes'))
            ->toBe(['first_name' => 'Grace'])
            ->not->toHaveKeys(['password', 'remember_token']);
    });

    test('a password-only change logs nothing', function (): void {
        $user = User::factory()->create();

        assertNoActivityLogsFor($user, 'updated');

        $user->update(['password' => 'another-S3cret!']);

        assertNoActivityLogsFor($user, 'updated');
    });

    test('a no-op save logs nothing', function (): void {
        $user = User::factory()->create();

        assertNoActivityLogsFor($user, 'updated');

        $user->update(['first_name' => $user->first_name]);

        assertNoActivityLogsFor($user, 'updated');
    });

    test('logs the deletion without credentials', function (): void {
        $user = User::factory()->create();

        assertNoActivityLogsFor($user, 'deleted');

        $user->delete();

        $deleted = getLastActivityLogsFor($user, 'deleted');
        assertActivityLogHasChanges($deleted);

        $changes = $deleted?->attribute_changes?->toArray() ?? [];

        expect($changes)->toHaveKey('old')->not->toHaveKey('attributes')
            ->and($changes['old'] ?? [])->not->toHaveKeys(['password', 'remember_token']);
    });

    test('records the authenticated causer', function (): void {
        $actor = User::factory()->create();
        $user = User::factory()->create();

        assertCountActivityLogsFor($user, 1);

        actingAs($actor);
        $user->update(['last_name' => 'Hopper']);

        assertCountActivityLogsFor($user, 2);

        $activity = getLastActivityLogsFor($user, 'updated');

        expect($activity?->causer_id)->toBe($actor->id)
            ->and($activity?->causer_type)->toBe(User::class);
    });
});
