<?php

declare(strict_types=1);

use App\Casts\AsMoney;
use Brick\Money\Context\CustomContext;
use Brick\Money\Money;
use Tests\Fixtures\Models\FixtureMoneyHost;

function moneyCastHost(): FixtureMoneyHost {
    return new FixtureMoneyHost;
}

describe('get', function (): void {
    test('reads a stored integer as Money at the fixed scale, using the sibling currency column', function (): void {
        $cast = new AsMoney;

        $money = $cast->get(moneyCastHost(), 'cost', 123456, ['transaction_currency' => 'USD']);

        expect($money)->toBeInstanceOf(Money::class)
            ->and((string) $money->getAmount())->toBe('12.3456')
            ->and($money->getCurrency()->getCurrencyCode())->toBe('USD');
    });

    test('preserves the fixed 4-decimal scale even for a currency with fewer minor units', function (): void {
        $cast = new AsMoney;

        $money = $cast->get(moneyCastHost(), 'cost', 1, ['transaction_currency' => 'JPY']);

        expect((string) $money->getAmount())->toBe('0.0001');
    });

    test('returns null unchanged', function (): void {
        $cast = new AsMoney;

        expect($cast->get(moneyCastHost(), 'cost', null, ['transaction_currency' => 'USD']))->toBeNull();
    });

    test('reads a value via a different currency column name', function (): void {
        $cast = new AsMoney('native_currency');

        $money = $cast->get(moneyCastHost(), 'attributed_sales', 500, ['native_currency' => 'EUR']);

        expect($money->getCurrency()->getCurrencyCode())->toBe('EUR');
    });

    test('throws when the sibling currency attribute is missing', function (): void {
        $cast = new AsMoney;

        expect(fn () => $cast->get(moneyCastHost(), 'cost', 123456, []))
            ->toThrow(RuntimeException::class, 'transaction_currency');
    });
});

describe('set', function (): void {
    test('stores a Money instance as an integer at the fixed scale', function (): void {
        $cast = new AsMoney;
        $money = Money::of('12.3456', 'USD', new CustomContext(4));

        $stored = $cast->set(moneyCastHost(), 'cost', $money, ['transaction_currency' => 'USD']);

        expect($stored)->toBe(123456);
    });

    test('rejects a Money instance whose currency does not match the row currency', function (): void {
        $cast = new AsMoney;
        $money = Money::of('12.3456', 'USD', new CustomContext(4));

        expect(fn () => $cast->set(moneyCastHost(), 'cost', $money, ['transaction_currency' => 'EUR']))
            ->toThrow(InvalidArgumentException::class, 'USD');
    });

    test('accepts a plain numeric amount without requiring the currency to be known yet', function (): void {
        $cast = new AsMoney;

        $stored = $cast->set(moneyCastHost(), 'cost', '12.3456', []);

        expect($stored)->toBe(123456);
    });

    test('rounds a finer-grained amount half up to the fixed scale', function (): void {
        $cast = new AsMoney;

        $stored = $cast->set(moneyCastHost(), 'cost', '12.34565', []);

        expect($stored)->toBe(123457);
    });

    test('returns null unchanged', function (): void {
        $cast = new AsMoney;

        expect($cast->set(moneyCastHost(), 'cost', null, []))->toBeNull();
    });
});

describe('round-trip', function (): void {
    test('a value written then read back is unchanged', function (): void {
        $cast = new AsMoney;
        $attributes = ['transaction_currency' => 'GBP'];

        $stored = $cast->set(moneyCastHost(), 'cost', '9.9900', $attributes);
        $money = $cast->get(moneyCastHost(), 'cost', $stored, $attributes);

        expect((string) $money->getAmount())->toBe('9.9900')
            ->and($money->getCurrency()->getCurrencyCode())->toBe('GBP');
    });
});
