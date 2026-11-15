<?php

declare(strict_types=1);

use App\Exceptions\SocialLoginException;

test('each named constructor carries a distinct message', function (): void {
    $messages = [
        SocialLoginException::missingEmail()->getMessage(),
        SocialLoginException::unverifiedEmail()->getMessage(),
        SocialLoginException::unverifiedLocalAccount()->getMessage(),
        SocialLoginException::registrationDisabled()->getMessage(),
    ];

    expect(array_unique($messages))->toHaveCount(4);
});
