<?php

declare(strict_types=1);

use App\Console\Commands\StarterKit\MarkerPatcher;

describe('inject', function (): void {
    test('inserts the fragment before the marker, indented like it', function (): void {
        $contents = "class A {\n    use Notifiable;\n    // @kit:user-traits\n}";

        $patched = (new MarkerPatcher)->inject($contents, 'user-traits', "use HasRoles;\nuse HasUuids;");

        expect($patched)->toBe("class A {\n    use Notifiable;\n    use HasRoles;\n    use HasUuids;\n    // @kit:user-traits\n}");
    });

    test('prefixes every line with the docblock asterisk when the marker is inside a docblock', function (): void {
        $contents = "/**\n * @property string \$id\n *\n * @kit:user-phpdoc\n */";

        $patched = (new MarkerPatcher)->inject($contents, 'user-phpdoc', "@property int \$a\n@property int \$b");

        expect($patched)->toBe("/**\n * @property string \$id\n *\n * @property int \$a\n * @property int \$b\n * @kit:user-phpdoc\n */");
    });

    test('keeps the marker so that other modules can target it too', function (): void {
        $patcher = new MarkerPatcher;
        $contents = "# @kit:services\n";

        $patched = $patcher->inject($patcher->inject($contents, 'services', 'redis:'), 'services', 'meilisearch:');

        expect($patched)->toBe("redis:\nmeilisearch:\n# @kit:services\n");
    });

    test('does not confuse a marker with another one sharing its prefix', function (): void {
        $contents = "// @kit:user\n// @kit:user-traits\n";

        $patched = (new MarkerPatcher)->inject($contents, 'user', 'x');

        expect($patched)->toBe("x\n// @kit:user\n// @kit:user-traits\n");
    });

    test('recognises the block comment flavour', function (): void {
        $patched = (new MarkerPatcher)->inject("/* @kit:imports */\n", 'imports', 'use Foo;');

        expect($patched)->toBe("use Foo;\n/* @kit:imports */\n");
    });

    test('fails when the marker is missing', function (): void {
        expect(fn () => (new MarkerPatcher)->inject("<?php\n", 'user-traits', 'x'))
            ->toThrow(RuntimeException::class, 'Marker [@kit:user-traits] not found.');
    });
});

describe('stripMarkers', function (): void {
    test('removes the marker lines and nothing else', function (): void {
        $contents = "a\n    // @kit:one\nb\n/* @kit:two */\nc";

        expect((new MarkerPatcher)->stripMarkers($contents))->toBe("a\nb\nc");
    });
});

describe('removeInactiveRegions', function (): void {
    test('drops the regions of the modules that are not active, markers included', function (): void {
        $contents = "a\n# @kit:begin codegraph\nb\n# @kit:end codegraph\nc";

        expect((new MarkerPatcher)->removeInactiveRegions($contents, ['core']))->toBe("a\nc");
    });

    test('leaves the regions of the active modules untouched', function (): void {
        $contents = "a\n# @kit:begin codegraph\nb\n# @kit:end codegraph\nc";

        expect((new MarkerPatcher)->removeInactiveRegions($contents, ['codegraph']))->toBe($contents);
    });

    test('drops a negated region when the module is active', function (): void {
        $contents = "a\n# @kit:begin !cloudflare\nb\n# @kit:end !cloudflare\nc";

        expect((new MarkerPatcher)->removeInactiveRegions($contents, ['cloudflare']))->toBe("a\nc")
            ->and((new MarkerPatcher)->removeInactiveRegions($contents, []))->toBe($contents);
    });

    test('fails when a dropped region is never closed', function (): void {
        (new MarkerPatcher)->removeInactiveRegions("# @kit:begin codegraph\nb", []);
    })->throws(RuntimeException::class, 'Region [@kit:begin codegraph] is never closed.');
});
