<?php

declare(strict_types=1);

use App\Console\Commands\StarterKit\ModuleManifest;

use function Safe\file_put_contents;
use function Safe\mkdir;

/**
 * @param  array<string, string>  $files
 */
function makeModuleDirectory(string $name, ?string $manifest, array $files = []): string {
    $directory = sys_get_temp_dir().'/kit_'.uniqid().'/'.$name;
    mkdir($directory, 0755, true);

    if ($manifest !== null) {
        file_put_contents($directory.'/module.php', "<?php\n\nreturn {$manifest};\n");
    }

    foreach ($files as $path => $contents) {
        mkdir(dirname($directory.'/'.$path), 0755, true);
        file_put_contents($directory.'/'.$path, $contents);
    }

    return $directory;
}

describe('load', function (): void {
    test('reads every section of the manifest', function (): void {
        $directory = makeModuleDirectory('demo', <<<'PHP'
[
    'require' => ['a/b' => '^1.0'],
    'require-dev' => ['c/d' => '^2.0'],
    'env' => ['ONE' => '1'],
    'env_local' => ['SECRET' => 'x'],
    'artisan' => ['php artisan demo:run'],
    'replace' => ['f.txt' => ['old' => 'new']],
    'fragments' => ['f.php' => ['marker' => 'code();']],
    'when_disabled' => ['remove_files' => ['a.md'], 'remove_packages' => ['a/b'], 'json' => ['x.json' => ['a.b' => null, 'c' => ['v']]]],
    'json' => ['composer' => ['extra.x' => 'y'], 'npm' => ['name' => 'z']],
]
PHP);

        $manifest = ModuleManifest::load($directory);

        expect($manifest->name)->toBe('demo')
            ->and($manifest->require)->toBe(['a/b' => '^1.0'])
            ->and($manifest->require_dev)->toBe(['c/d' => '^2.0'])
            ->and($manifest->env)->toBe(['ONE' => '1'])
            ->and($manifest->env_local)->toBe(['SECRET' => 'x'])
            ->and($manifest->artisan)->toBe(['php artisan demo:run'])
            ->and($manifest->replace)->toBe(['f.txt' => ['old' => 'new']])
            ->and($manifest->fragments)->toBe(['f.php' => ['marker' => 'code();']])
            ->and($manifest->remove_files)->toBe(['a.md'])
            ->and($manifest->remove_packages)->toBe(['a/b'])
            ->and($manifest->json_when_disabled)->toBe(['x.json' => ['a.b' => null, 'c' => ['v']]])
            ->and($manifest->composer_config)->toBe(['extra.x' => 'y'])
            ->and($manifest->npm_config)->toBe(['name' => 'z']);
    });

    test('treats a module without manifest as empty', function (): void {
        $manifest = ModuleManifest::load(makeModuleDirectory('files-only', null));

        expect($manifest->require)->toBe([])
            ->and($manifest->fragments)->toBe([]);
    });

    test('reads a fragment from the file its value points to', function (): void {
        $directory = makeModuleDirectory('demo', "['fragments' => ['f.php' => ['a' => 'fragments/a.php', 'b' => 'use Inline;']]]", ['fragments/a.php' => "line1();\nline2();\n"]);

        expect(ModuleManifest::load($directory)->fragments)->toBe(['f.php' => ['a' => "line1();\nline2();", 'b' => 'use Inline;']]);
    });

    test('merges the chosen variants on top of the base manifest', function (): void {
        $directory = makeModuleDirectory('demo', <<<'PHP'
[
    'require' => ['base/pkg' => '^1.0'],
    'env' => ['BASE' => '1'],
    'artisan' => ['one'],
    'fragments' => ['f.php' => ['m' => 'base();']],
    'variants' => [
        'x' => ['require' => ['x/pkg' => '^2.0'], 'env' => ['X' => '1'], 'artisan' => ['two'], 'fragments' => ['f.php' => ['m' => 'x();']]],
        'y' => ['env' => ['Y' => '1']],
        'none' => [],
    ],
]
PHP);

        $manifest = ModuleManifest::load($directory, ['x', 'y']);

        expect($manifest->require)->toBe(['base/pkg' => '^1.0', 'x/pkg' => '^2.0'])
            ->and($manifest->env)->toBe(['BASE' => '1', 'X' => '1', 'Y' => '1'])
            ->and($manifest->artisan)->toBe(['one', 'two'])
            ->and($manifest->fragments)->toBe(['f.php' => ['m' => "base();\nx();"]])
            ->and(ModuleManifest::load($directory)->require)->toBe(['base/pkg' => '^1.0']);
    });

    test('rejects a variant the module does not define', function (): void {
        $directory = makeModuleDirectory('demo', "['variants' => ['x' => []]]");

        expect(fn () => ModuleManifest::load($directory, ['nope']))
            ->toThrow(InvalidArgumentException::class, 'Module [demo] has no variant [nope].');
    });

    test('rejects a manifest that does not return an array', function (): void {
        $directory = makeModuleDirectory('demo', "'text'");

        expect(fn () => ModuleManifest::load($directory))
            ->toThrow(InvalidArgumentException::class, 'Module [demo] manifest must return an array.');
    });
});
