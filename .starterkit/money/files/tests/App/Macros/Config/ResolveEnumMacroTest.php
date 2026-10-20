<?php

declare(strict_types=1);

use App\Macros\Config\ResolveEnumMacro;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Config;

test('registers on the Config facade under "enum"', function (): void {
    expect((new ReflectionMethod(ResolveEnumMacro::class, 'macroable'))->invoke(null))->toBe(Config::class)
        ->and((new ReflectionMethod(ResolveEnumMacro::class, 'name'))->invoke(null))->toBe('enum');
});

test('returns the enum case stored in config', function (): void {
    config()->set('fixture.mode', RoundingMode::HalfUp);

    expect(ResolveEnumMacro::handle('fixture.mode', RoundingMode::class))->toBe(RoundingMode::HalfUp);
});

test('throws when the value is not a case of the enum', function (): void {
    config()->set('fixture.mode', 'half_up');

    ResolveEnumMacro::handle('fixture.mode', RoundingMode::class);
})->throws(InvalidArgumentException::class);
