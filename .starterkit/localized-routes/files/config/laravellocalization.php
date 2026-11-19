<?php

declare(strict_types=1);

use App\Enums\Locale;

/*
 * Only what differs from the package defaults (the rest is merged in): the supported locales come
 * from the `Locale` enum, and every locale, the default one too, has its own URL prefix.
 */
return [
    'supportedLocales' => collect(Locale::cases())
        ->mapWithKeys(fn (Locale $locale): array => [
            $locale->value => [
                'name' => ucfirst($locale->name),
                'script' => $locale->script(),
                'native' => ucfirst($locale->name),
                'regional' => $locale->value.'_'.mb_strtoupper($locale->value),
            ],
        ])
        ->all(),

    'useAcceptLanguageHeader' => false,

    'hideDefaultLocaleInURL' => false,
];
