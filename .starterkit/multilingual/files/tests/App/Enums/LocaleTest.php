<?php

declare(strict_types=1);

use App\Enums\Locale;

test('the default locale is a case', function (): void {
    expect(Locale::tryFrom('__KIT_DEFAULT_LOCALE__'))->toBe(Locale::__KIT_DEFAULT_LOCALE_CASE__);
});

test('every case is backed by a lowercase locale code', function (): void {
    foreach (Locale::cases() as $locale) {
        expect($locale->value)->toBe(mb_strtolower($locale->value));
    }
});
