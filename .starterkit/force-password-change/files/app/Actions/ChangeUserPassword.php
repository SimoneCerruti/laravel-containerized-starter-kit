<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use SensitiveParameter;

final class ChangeUserPassword {
    use AsAction;

    /**
     * Self-service only: the caller (the profile page) passes the signed-in user.
     *
     * $new_password may already be a bcrypt hash (the profile page's password field hashes it
     * before dehydration, so the session's cached password hash stays in sync) or plain text —
     * User's `hashed` cast only re-hashes a value Hash::isHashed() doesn't already recognise.
     */
    public function handle(User $user, #[SensitiveParameter] string $current_password, #[SensitiveParameter] string $new_password): void {
        throw_unless(Hash::check($current_password, $user->password), ValidationException::withMessages([
            'current_password' => Lang::string('The provided password does not match your current password.'),
        ]));

        DB::transaction(function () use ($user, $new_password): void {
            $user->forceFill([
                'password' => $new_password,
                'password_changed_at' => now(),
            ])->save();

            activity()
                ->performedOn($user)
                ->event('password_changed')
                ->log('Password was changed.');
        });
    }
}
