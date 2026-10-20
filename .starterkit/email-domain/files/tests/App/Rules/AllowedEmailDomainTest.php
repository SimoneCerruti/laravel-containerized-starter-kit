<?php

declare(strict_types=1);

use App\Rules\AllowedEmailDomain;
use Illuminate\Support\Facades\Validator;

beforeEach(function (): void {
    config()->set('app.allowed_admin_email_domains', ['example.com']);
});

test('passes for an allowed domain', function (): void {
    $validator = Validator::make(['email' => 'jane@example.com'], ['email' => new AllowedEmailDomain]);

    expect($validator->passes())->toBeTrue();
});

test('fails for another domain', function (): void {
    $validator = Validator::make(['email' => 'jane@other.test'], ['email' => new AllowedEmailDomain]);

    expect($validator->fails())->toBeTrue();
});

test('fails for a value that is not a string', function (): void {
    $validator = Validator::make(['email' => ['jane@example.com']], ['email' => new AllowedEmailDomain]);

    expect($validator->fails())->toBeTrue();
});
