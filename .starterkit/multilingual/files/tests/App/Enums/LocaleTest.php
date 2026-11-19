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

test('every case has a four-letter ISO 15924 script code', function (Locale $locale): void {
    expect($locale->script())->toMatch('/^[A-Z][a-z]{3}$/');
})->with(Locale::cases());

test('a language written in a non-Latin script reports it', function (Locale $locale): void {
    $non_latin = ['ar' => 'Arab', 'ru' => 'Cyrl', 'el' => 'Grek', 'he' => 'Hebr', 'hi' => 'Deva', 'ja' => 'Jpan', 'ko' => 'Kore', 'th' => 'Thai', 'zh' => 'Hans'];

    expect($locale->script())->toBe($non_latin[$locale->value] ?? 'Latn');
})->with(Locale::cases());
