<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

use Illuminate\Support\Facades\Lang;

/**
 * Derives a label from the enum's snake_case backing value, then translates it.
 * Only fits enums where every case's label is exactly that transformation —
 * enums with acronyms, proper nouns, or reworded labels keep a manual getLabel().
 *
 * @phpstan-require-implements \BackedEnum
 */
trait HasAutoLabel {
    public function getLabel(): string {
        return Lang::string(str($this->value)->replace('_', ' ')->ucfirst()->toString());
    }
}
