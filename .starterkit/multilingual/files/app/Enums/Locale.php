<?php

declare(strict_types=1);

namespace App\Enums;

enum Locale: string {
__KIT_LOCALE_CASES__

    /**
     * ISO 15924 code of the script the language is written in. Latin unless the language is listed.
     */
    public function script(): string {
        return match ($this->value) {
            'ar', 'fa', 'ps', 'ur' => 'Arab',
            'am' => 'Ethi',
            'bg', 'be', 'kk', 'mk', 'mn', 'ru', 'sr', 'tg', 'uk' => 'Cyrl',
            'bn' => 'Beng',
            'el' => 'Grek',
            'he', 'yi' => 'Hebr',
            'hi', 'mr', 'ne' => 'Deva',
            'hy' => 'Armn',
            'ja' => 'Jpan',
            'ka' => 'Geor',
            'ko' => 'Kore',
            'ta' => 'Taml',
            'te' => 'Telu',
            'th' => 'Thai',
            'zh' => 'Hans',
            default => 'Latn',
        };
    }
}
