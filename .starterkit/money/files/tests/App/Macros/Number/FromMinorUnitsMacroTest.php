<?php

declare(strict_types=1);

use App\Macros\Number\FromMinorUnitsMacro;
use Illuminate\Support\Number;

test('registers on the Number class under "fromMinorUnits"', function (): void {
    expect((new ReflectionMethod(FromMinorUnitsMacro::class, 'macroable'))->invoke(null))->toBe(Number::class)
        ->and((new ReflectionMethod(FromMinorUnitsMacro::class, 'name'))->invoke(null))->toBe('fromMinorUnits');
});

test('moves the decimal point back by the configured scale', function (): void {
    config()->set('money.minor_unit_scale', 4);

    expect((string) FromMinorUnitsMacro::handle(123456))->toBe('12.3456');
});
