<?php

declare(strict_types=1);

use App\Actions\ChangeUserPassword;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Safe\json_encode;

mutates(ChangeUserPassword::class);

it('changes the password of the authenticated user', function (): void {
    $user = User::factory()->withInitialPassword()->create(['password' => Hash::make('old-password')]);
    actingAs($user);

    expect(Hash::check('new-password-123', $user->password))->toBeFalse()
        ->and($user->password_changed_at)->toBeNull();

    ChangeUserPassword::run($user, 'old-password', 'new-password-123');

    $user->refresh();

    expect(Hash::check('new-password-123', $user->password))->toBeTrue()
        ->and($user->password_changed_at)->not->toBeNull();
});

it('throws a validation exception when the current password does not match', function (): void {
    $user = User::factory()->withInitialPassword()->create(['password' => Hash::make('old-password')]);
    actingAs($user);

    expect(fn () => ChangeUserPassword::run($user, 'wrong-password', 'new-password-123'))
        ->toThrow(ValidationException::class, 'The provided password does not match your current password.')
        ->and(Hash::check('new-password-123', $user->refresh()->password))->toBeFalse()
        ->and($user->password_changed_at)->toBeNull();
});

it('records a password_changed activity-log entry on the user without leaking the password', function (): void {
    $user = User::factory()->withInitialPassword()->create(['password' => Hash::make('old-password')]);
    actingAs($user);

    ChangeUserPassword::run($user, 'old-password', 'new-password-123');

    /** @var Activity|null $activity */
    $activity = Activity::query()->where('subject_id', $user->id)->where('event', 'password_changed')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_type)->toBe($user->getMorphClass())
        ->and(json_encode($activity->getAttributes()))->not->toContain('new-password-123')
        ->and(json_encode($activity->getAttributes()))->not->toContain('old-password');
});
