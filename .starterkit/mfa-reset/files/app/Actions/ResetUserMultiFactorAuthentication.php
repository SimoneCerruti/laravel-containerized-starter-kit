<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

final class ResetUserMultiFactorAuthentication {
    use AsAction;

    public function handle(User $user): void {
        $user->forceFill([
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
            'has_email_authentication' => false,
        ])->save();
    }
}
