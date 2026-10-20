<?php

declare(strict_types=1);

use App\Exceptions\SocialLoginRejected;

test('each named constructor carries a distinct message', function (): void {
    $messages = [
        SocialLoginRejected::missingEmail()->getMessage(),
        SocialLoginRejected::unverifiedEmail()->getMessage(),
        SocialLoginRejected::unverifiedLocalAccount()->getMessage(),
        SocialLoginRejected::registrationDisabled()->getMessage(),
    ];

    expect(array_unique($messages))->toHaveCount(4);
});
