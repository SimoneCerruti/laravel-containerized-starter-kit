<?php

declare(strict_types=1);

namespace App\Console\Commands\StarterKit;

use function Safe\preg_match_all;

/**
 * Replaces `__KIT_*__` placeholders, in file contents and in file paths.
 */
final class PlaceholderReplacer {
    /**
     * @param  array<string, string>  $values  keyed by placeholder name, eg `KIT_APP_NAME`
     */
    public function __construct(private readonly array $values) {}

    public function replace(string $subject): string {
        return strtr($subject, collect($this->values)
            ->mapWithKeys(fn (string $value, string $name): array => ["__{$name}__" => $value])
            ->all());
    }

    /**
     * @return list<string> the placeholders of the given text that no value was provided for
     */
    public function findUnresolved(string $subject): array {
        preg_match_all('/__KIT_[A-Z0-9_]+__/', $this->replace($subject), $matches);

        return array_values(array_unique($matches[0]));
    }
}
