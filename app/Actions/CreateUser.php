<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

final class CreateUser {
    use AsAction;

    /**
     * Creates a user whose email is already verified, so the first administrator can sign in
     * on an environment where nothing else exists yet.
     */
    public function handle(string $first_name, string $last_name, string $email, string $password): User {
        return DB::transaction(function () use ($first_name, $last_name, $email, $password): User {
            $user = User::query()->create([
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'password' => $password,
            ]);

            $user->markEmailAsVerified();

            // @kit:create-user

            return $user;
        });
    }
}
