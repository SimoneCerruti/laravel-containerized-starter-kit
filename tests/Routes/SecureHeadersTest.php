<?php

declare(strict_types=1);

use function Pest\Laravel\get;
use function Safe\putenv;

describe('public page', function (): void {
    test('carries the security headers', function (): void {
        get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    });

    test('starts the content security policy in report-only mode', function (): void {
        $response = get('/')->assertOk();

        expect($response->headers->get('Content-Security-Policy-Report-Only'))->toContain("default-src 'self'")
            ->and($response->headers->has('Content-Security-Policy'))->toBeFalse();
    });

    test('does not send HSTS outside staging and production', function (): void {
        get('/')->assertOk()->assertHeaderMissing('Strict-Transport-Security');
    });
});

test('enables HSTS only in staging and production', function (string $environment, bool $expected): void {
    $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = $environment;
    putenv("APP_ENV={$environment}");

    $config = require config_path('secure-headers.php');

    expect($config['hsts']['enable'])->toBe($expected);
})->with([
    'local' => ['local', false],
    'testing' => ['testing', false],
    'staging' => ['staging', true],
    'production' => ['production', true],
])->after(function (): void {
    $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
    putenv('APP_ENV=testing');
});
