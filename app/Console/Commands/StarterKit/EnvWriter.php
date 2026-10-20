<?php

declare(strict_types=1);

namespace App\Console\Commands\StarterKit;

use function Safe\preg_match;
use function Safe\preg_replace_callback;

/**
 * Sets `KEY=value` entries in the contents of an env file.
 *
 * An existing entry is replaced in place; a new one goes right before the `@kit:env` marker
 * when the file has it, otherwise at the end of the file.
 */
final class EnvWriter {
    /**
     * @param  array<string, string>  $values
     */
    public function apply(string $contents, array $values): string {
        foreach ($values as $key => $value) {
            $line = $key.'='.$this->format($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $contents) === 1) {
                $contents = (string) preg_replace_callback($pattern, fn (): string => $line, $contents, 1);

                continue;
            }

            $contents = $this->append($contents, $line);
        }

        return $contents;
    }

    private function append(string $contents, string $line): string {
        if (preg_match('/^.*@kit:env\b.*$/m', $contents, $marker, PREG_OFFSET_CAPTURE) === 1) {
            return substr_replace($contents, $line."\n", $marker[0][1], 0);
        }

        return rtrim($contents, "\n").($contents === '' ? '' : "\n").$line."\n";
    }

    private function format(string $value): string {
        if (preg_match('/[\s#"\'$\\\\]/', $value) !== 1) {
            return $value;
        }

        return '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"', '$' => '\\$']).'"';
    }
}
