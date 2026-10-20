<?php

declare(strict_types=1);

namespace App\Macros\Config;

use App\Macros\Contracts\Macro;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use InvalidArgumentException;
use UnitEnum;

/**
 * Adds `Config::enum()`: reads a config value that is an enum case (e.g. `money.rounding_mode`).
 */
final class ResolveEnumMacro extends Macro {
    protected static function name(): string {
        return 'enum';
    }

    protected static function macroable(): string {
        return Config::class;
    }

    /**
     * @template TEnum of UnitEnum
     *
     * @param  class-string<TEnum>  $enum
     * @return TEnum
     */
    public static function handle(string $key, string $enum): UnitEnum {
        $value = Config::get($key);

        throw_unless($value instanceof $enum, InvalidArgumentException::class, \sprintf(
            'Config key [%s] must resolve to a %s case, got %s.',
            Str::escapeControlCharacters($key),
            $enum,
            \get_debug_type($value),
        ));

        return $value;
    }
}
