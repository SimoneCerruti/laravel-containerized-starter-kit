<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Support\Facades\Lang;
use RuntimeException;

final class SocialLoginRejected extends RuntimeException {
    public static function missingEmail(): self {
        return new self(Lang::string('The provider did not share an email address.'));
    }

    public static function unverifiedEmail(): self {
        return new self(Lang::string('The provider could not confirm that this email address is verified.'));
    }

    public static function unverifiedLocalAccount(): self {
        return new self(Lang::string('An account with this email already exists. Verify it before signing in with a provider.'));
    }

    public static function registrationDisabled(): self {
        return new self(Lang::string('No account exists for this email address.'));
    }
}
