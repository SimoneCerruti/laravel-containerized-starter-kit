<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\CreateUser;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('users:create {--first-name= : First name} {--last-name= : Last name} {--email= : Email address}')]
#[Description('Create a user with a verified email, e.g. the first administrator of an environment')]
final class CreateUserCommand extends Command {
    public function handle(): int {
        $first_name = (string) ($this->option('first-name') ?: text('First name', required: true));
        $last_name = (string) ($this->option('last-name') ?: text('Last name', required: true));
        $email = (string) ($this->option('email') ?: text('Email', required: true, validate: ['email' => 'required|email']));

        if (User::query()->where('email', $email)->exists()) {
            $this->components->error("A user with the email {$email} already exists.");

            return self::FAILURE;
        }

        $user = CreateUser::run($first_name, $last_name, $email, password(
            'Password',
            required: true,
            validate: ['password' => ['required', Password::min(12)]],
        ));

        $this->components->info("User {$user->email} created.");

        return self::SUCCESS;
    }
}
