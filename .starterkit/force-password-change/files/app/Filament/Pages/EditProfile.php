<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\ChangeUserPassword;
use App\Models\User;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Self-service password change only: the base page's name/email fields are dropped, since profile
 * data is managed by admins. The MFA management section (rendered by the
 * base class's content()/getMultiFactorAuthenticationContentComponent()) is left untouched.
 */
final class EditProfile extends BaseEditProfile {
    public function form(Schema $schema): Schema {
        return $schema
            ->components([
                $this->getCurrentPasswordFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    protected function getCurrentPasswordFormComponent(): Component {
        return TextInput::make('currentPassword')
            ->label(Lang::string('Current password'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->autocomplete('current-password')
            ->columnSpanFull();
    }

    protected function getPasswordFormComponent(): Component {
        return TextInput::make('password')
            ->label(Lang::string('New password'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->rule(Password::default())
            ->showAllValidationMessages()
            ->autocomplete('new-password')
            // Hashed here (not left to the `password` cast) so the session's password_hash_*
            // entry the base save() writes right after commit — the mechanism that keeps
            // AuthenticateSession from logging this same session out — matches the persisted hash.
            ->dehydrateStateUsing(fn (#[SensitiveParameter] string $state): string => Hash::make($state))
            ->same('passwordConfirmation')
            ->columnSpanFull();
    }

    protected function getPasswordConfirmationFormComponent(): Component {
        return TextInput::make('passwordConfirmation')
            ->label(Lang::string('Confirm new password'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->autocomplete('new-password')
            ->dehydrated(false)
            ->columnSpanFull();
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model {
        /** @var User $record */
        try {
            ChangeUserPassword::run($record, (string) $data['currentPassword'], (string) $data['password']);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? $exception->getMessage();

            throw ValidationException::withMessages(['data.currentPassword' => $message]);
        }

        return $record;
    }
}
