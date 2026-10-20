<?php

declare(strict_types=1);

namespace App\Macros\Number;

use App\Macros\Contracts\Macro;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Number;

final class FromMinorUnitsMacro extends Macro {
    protected static function macroable(): string {
        return Number::class;
    }

    protected static function name(): string {
        return 'fromMinorUnits';
    }

    public static function handle(int|string $amount): BigDecimal {
        return BigDecimal::of($amount)->withPointMovedLeft(Config::integer('money.minor_unit_scale'));
    }
}
