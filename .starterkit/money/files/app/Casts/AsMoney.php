<?php

declare(strict_types=1);

namespace App\Casts;

use Brick\Math\BigDecimal;
use Brick\Money\Context\CustomContext;
use Brick\Money\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Casts an integer column to a `Brick\Money\Money` and back, at the fixed scale of `money.minor_unit_scale`
 * (unlike `Money::ofMinor()`, it does not vary with the currency).
 * The currency is read from a sibling column (default `transaction_currency`).
 *
 * @implements CastsAttributes<Money, Money|string|int>
 */
final class AsMoney implements CastsAttributes {
    public function __construct(private readonly string $currency_column = 'transaction_currency') {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money {
        if ($value === null) {
            return null;
        }

        $currency = $attributes[$this->currency_column] ?? null;

        throw_if($currency === null, RuntimeException::class, \sprintf(
            'AsMoney cast on [%s] needs a [%s] attribute on the same row to resolve the currency, but none was set.',
            $key,
            $this->currency_column,
        ));

        $scale = Config::integer('money.minor_unit_scale');

        $amount = Number::fromMinorUnits((string) $value);

        return Money::of($amount, $currency, new CustomContext($scale));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Money) {
            $row_currency = $attributes[$this->currency_column] ?? null;

            throw_if(
                $row_currency !== null && $value->getCurrency()->getCurrencyCode() !== $row_currency,
                InvalidArgumentException::class,
                \sprintf(
                    'Cannot set [%s] to a %s amount on a row whose %s is %s.',
                    $key,
                    Str::escapeControlCharacters($value->getCurrency()->getCurrencyCode()),
                    $this->currency_column,
                    Str::escapeControlCharacters((string) $row_currency),
                ),
            );

            $amount = $value->getAmount();
        } else {
            $amount = BigDecimal::of((string) $value);
        }

        return Number::toMinorUnits($amount);
    }
}
