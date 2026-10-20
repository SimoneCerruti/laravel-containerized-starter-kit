<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\App;

test('blocks a non-local environment', function (): void {
    App::detectEnvironment(fn (): string => 'production');

    expect(fn () => (new DatabaseSeeder)->run())->toThrow(RuntimeException::class);
});
