<?php

declare(strict_types=1);

use App\Console\Commands\StarterKit\RulesIndexBuilder;

use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\mkdir;

describe('build', function (): void {
    test('lists the rule files with their paths, ignoring the old index and files without paths', function (): void {
        $directory = sys_get_temp_dir().'/rules_'.uniqid();
        mkdir($directory);
        file_put_contents($directory.'/b.md', "---\npaths:\n    - tests/**\n    - app/X/**\n---\n# B\n");
        file_put_contents($directory.'/a.md', "---\npaths:\n    - app/A/**\n---\n# A\n");
        file_put_contents($directory.'/loose.md', "# No frontmatter\n");
        file_put_contents($directory.'/index.md', 'old');

        $index = (new RulesIndexBuilder)->build($directory);

        expect($index)->toContain("| app/A/** | .ai/rules/a.md |\n| tests/**, app/X/** | .ai/rules/b.md |\n")
            ->not->toContain('loose.md')
            ->not->toContain('index.md');
    });

    test('regenerates the committed index identically from the rule files', function (): void {
        $rebuilt = (new RulesIndexBuilder)->build(base_path('.ai/rules'));

        expect($rebuilt)->toBe((string) file_get_contents(base_path('.ai/rules/index.md')));
    });
});
