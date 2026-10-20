<?php

declare(strict_types=1);

use App\Actions\CheckAllowedEmailForAdminPanel;

beforeEach(function (): void {
    config()->set('app.allowed_admin_email_domains', ['example.com']);
});

test('allows an email on an allowed domain, whatever its case', function (): void {
    expect(CheckAllowedEmailForAdminPanel::run('Jane@Example.COM'))->toBeTrue();
});

test('rejects an email on another domain', function (): void {
    expect(CheckAllowedEmailForAdminPanel::run('jane@other.test'))->toBeFalse();
});
