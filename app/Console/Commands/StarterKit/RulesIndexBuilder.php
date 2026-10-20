<?php

declare(strict_types=1);

namespace App\Console\Commands\StarterKit;

use function Safe\file_get_contents;
use function Safe\glob;
use function Safe\preg_match;
use function Safe\preg_match_all;

/**
 * Builds `.ai/rules/index.md` from the `paths:` frontmatter of the rule files that are left.
 */
final class RulesIndexBuilder {
    public function build(string $rules_directory): string {
        $rows = collect(glob($rules_directory.'/*.md'))
            ->reject(fn (string $file): bool => basename($file) === 'index.md')
            ->sort()
            ->map(fn (string $file): array => ['file' => basename($file), 'paths' => $this->readPaths((string) file_get_contents($file))])
            ->reject(fn (array $rule): bool => $rule['paths'] === [])
            ->map(fn (array $rule): string => '| '.implode(', ', $rule['paths']).' | .ai/rules/'.$rule['file'].' |')
            ->implode("\n");

        return "# Project Rules Index\n\nBefore planning or editing, find the row whose globs match the file's path and read that rule file.\n\n| Applies to | Rule file |\n| --- | --- |\n{$rows}\n";
    }

    /**
     * @return list<string>
     */
    private function readPaths(string $contents): array {
        if (preg_match('/\A---\R(.*?)\R---/s', $contents, $frontmatter) !== 1) {
            return [];
        }

        preg_match_all('/^\s+-\s+(\S.*?)\s*$/m', $frontmatter[1], $matches);

        return array_map(fn (string $path): string => trim($path, '"\''), $matches[1]);
    }
}
