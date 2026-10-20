<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ResetUserMultiFactorAuthentication;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('users:reset-mfa {email : Email of the user who lost the second factor}')]
#[Description('Remove every multi-factor authentication method from a user')]
final class ResetUserMultiFactorAuthenticationCommand extends Command {
    public function handle(): int {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user instanceof User) {
            $this->components->error('User not found.');

            return self::FAILURE;
        }

        ResetUserMultiFactorAuthentication::run($user);

        $this->components->info("Multi-factor authentication reset for {$user->email}.");

        return self::SUCCESS;
    }
}
