<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

use Filament\Support\Contracts\HasLabel;

/**
 * Filament `->options()` map that leaves out the cases returned by `excludedFromSelectableOptions()`,
 * except the current value, so existing records holding an excluded case still validate.
 *
 * @phpstan-require-implements HasLabel
 * @phpstan-require-implements \BackedEnum
 */
trait HasSelectableOptions {
    /**
     * @return array<string, string>
     */
    public static function selectableOptions(?self $current = null): array {
        $excluded = static::excludedFromSelectableOptions();

        return collect(static::cases())
            ->filter(fn (self $case): bool => ! \in_array($case, $excluded) || $case === $current)
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->getLabel()])
            ->all();
    }

    /**
     * @return list<self>
     */
    public static function excludedFromSelectableOptions(): array {
        return [];
    }
}
