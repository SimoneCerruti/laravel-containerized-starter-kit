<?php

declare(strict_types=1);

namespace App\Console\Commands\StarterKit;

use RuntimeException;

use function Safe\preg_match;

/**
 * Injects code fragments into files that carry `@kit:<name>` marker comments
 *
 * (`// @kit:name`, `/* @kit:name *​/`, `# @kit:name`).
 *
 * A fragment is inserted on the lines right before its marker, indented like the marker
 * (inside a docblock every line also gets the leading `* `).
 * The marker stays in place so several modules can target the same spot;
 * `stripMarkers()` removes the leftovers once the install is done.
 */
final class MarkerPatcher {
    public function inject(string $contents, string $marker, string $fragment): string {
        $lines = explode("\n", $contents);

        foreach ($lines as $index => $line) {
            if (! $this->lineHoldsMarker($line, $marker)) {
                continue;
            }

            $indentation = str($line)->match('/^\s*(?:\*\s)?/')->toString();
            $indented_fragment = collect(explode("\n", rtrim($fragment, "\n")))
                ->map(fn (string $fragment_line): string => $fragment_line === '' ? '' : $indentation.$fragment_line)
                ->all();

            array_splice($lines, $index, 0, $indented_fragment);

            return implode("\n", $lines);
        }

        throw new RuntimeException("Marker [@kit:{$marker}] not found.");
    }

    /**
     * Drops the regions wrapped in `@kit:begin <module>` / `@kit:end <module>` whose module is not active
     * (`!<module>` regions are dropped when the module is active). The markers of the kept regions are
     * left for `stripMarkers()`.
     *
     * @param  list<string>  $active
     */
    public function removeInactiveRegions(string $contents, array $active): string {
        $kept = [];
        $skipping = null;

        foreach (explode("\n", $contents) as $line) {
            if ($skipping !== null) {
                if (preg_match('/@kit:end\s+'.preg_quote($skipping, '/').'(?![\w-])/', $line) === 1) {
                    $skipping = null;
                }

                continue;
            }

            if (preg_match('/@kit:begin\s+(!?[\w-]+)/', $line, $matches) === 1) {
                $region = $matches[1];
                $is_active = \in_array(ltrim($region, '!'), $active, true);

                if (str_starts_with($region, '!') ? $is_active : ! $is_active) {
                    $skipping = $region;

                    continue;
                }
            }

            $kept[] = $line;
        }

        if ($skipping !== null) {
            throw new RuntimeException("Region [@kit:begin {$skipping}] is never closed.");
        }

        return implode("\n", $kept);
    }

    public function stripMarkers(string $contents): string {
        return collect(explode("\n", $contents))
            ->reject(fn (string $line): bool => preg_match('/@kit:[\w-]+/', $line) === 1)
            ->implode("\n");
    }

    private function lineHoldsMarker(string $line, string $marker): bool {
        return preg_match('/@kit:'.preg_quote($marker, '/').'(?![\w-])/', $line) === 1;
    }
}
