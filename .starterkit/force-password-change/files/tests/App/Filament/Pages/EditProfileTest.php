<?php

declare(strict_types=1);

use App\Filament\Pages\EditProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

mutates(EditProfile::class);

describe('EditProfile', function (): void {
    it('renders for an authenticated user', function (): void {
        actingAs(User::factory()->create());

        livewire(EditProfile::class)
            ->assertSuccessful();
    });

    it('changes the password of the authenticated user', function (): void {
        $user = User::factory()->withInitialPassword()->create();
        actingAs($user);

        expect($user->password_changed_at)->toBeNull();

        livewire(EditProfile::class)
            ->fillForm([
                'currentPassword' => 'password',
                'password' => 'Str0ng-Test-Passw0rd!',
                'passwordConfirmation' => 'Str0ng-Test-Passw0rd!',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        expect(Hash::check('Str0ng-Test-Passw0rd!', $user->password))->toBeTrue()
            ->and($user->password_changed_at)->not->toBeNull();
    });

    it('cannot bypass the current-password check', function (): void {
        $user = User::factory()->create();
        actingAs($user);

        livewire(EditProfile::class)
            ->fillForm([
                'currentPassword' => 'wrong-password',
                'password' => 'Str0ng-Test-Passw0rd!',
                'passwordConfirmation' => 'Str0ng-Test-Passw0rd!',
            ])
            ->call('save')
            ->assertHasFormErrors(['currentPassword']);

        expect(Hash::check('Str0ng-Test-Passw0rd!', $user->refresh()->password))->toBeFalse();
    });

    it('requires the new password and its confirmation to match', function (): void {
        actingAs(User::factory()->create());

        livewire(EditProfile::class)
            ->fillForm([
                'currentPassword' => 'password',
                'password' => 'Str0ng-Test-Passw0rd!',
                'passwordConfirmation' => 'Different-Passw0rd!',
            ])
            ->call('save')
            ->assertHasFormErrors(['password' => 'same']);
    });

    it('rejects a new password that does not meet the strength requirements', function (): void {
        actingAs(User::factory()->create());

        livewire(EditProfile::class)
            ->fillForm([
                'currentPassword' => 'password',
                'password' => 'weak',
                'passwordConfirmation' => 'weak',
            ])
            ->call('save')
            ->assertHasFormErrors(['password' => Password::class]);
    });

    it('requires the current password and the new password', function (): void {
        actingAs(User::factory()->create());

        livewire(EditProfile::class)
            ->fillForm([
                'currentPassword' => '',
                'password' => '',
                'passwordConfirmation' => '',
            ])
            ->call('save')
            ->assertHasFormErrors([
                'currentPassword' => 'required',
                'password' => 'required',
            ]);
    });
});
