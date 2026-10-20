<?php

declare(strict_types=1);

use App\Macros\Number\ToMinorUnitsMacro;
use Brick\Math\BigDecimal;
use Illuminate\Support\Number;

test('registers on the Number class under "toMinorUnits"', function (): void {
    expect((new ReflectionMethod(ToMinorUnitsMacro::class, 'macroable'))->invoke(null))->toBe(Number::class)
        ->and((new ReflectionMethod(ToMinorUnitsMacro::class, 'name'))->invoke(null))->toBe('toMinorUnits');
});

test('moves the decimal point by the configured scale', function (): void {
    config()->set('money.minor_unit_scale', 4);

    expect(ToMinorUnitsMacro::handle(BigDecimal::of('12.3456')))->toBe(123456);
});
