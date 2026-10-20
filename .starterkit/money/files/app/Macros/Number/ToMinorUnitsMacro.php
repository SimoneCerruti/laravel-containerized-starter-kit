<?php

declare(strict_types=1);

namespace App\Macros\Number;

use App\Macros\Contracts\Macro;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Number;

final class ToMinorUnitsMacro extends Macro {
    protected static function macroable(): string {
        return Number::class;
    }

    protected static function name(): string {
        return 'toMinorUnits';
    }

    public static function handle(BigDecimal $amount): int {
        $scale = Config::integer('money.minor_unit_scale');

        return $amount->toScale($scale, Config::enum('money.rounding_mode', RoundingMode::class))
            ->withPointMovedRight($scale)
            ->toInt();
    }
}
