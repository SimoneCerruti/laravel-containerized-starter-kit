<?php

declare(strict_types=1);

use App\Console\Commands\StarterKit\ModuleManifest;
use Symfony\Component\Finder\Finder;

use function Safe\file_get_contents;

/**
 * @return list<string>
 */
function shippedModulePaths(): array {
    return collect(Finder::create()->directories()->depth(0)->in(dirname(__DIR__, 5).'/.starterkit'))
        ->map(fn (SplFileInfo $directory): string => $directory->getPathname())
        ->values()
        ->all();
}

/**
 * @return list<string>
 */
function allVariantsOf(string $module_path): array {
    if (! is_file("{$module_path}/module.php")) {
        return [];
    }

    $manifest = require "{$module_path}/module.php";

    return array_map(strval(...), array_keys($manifest['variants'] ?? []));
}

/**
 * Everything a marker can live in: the project file, module stubs for the same path and every fragment.
 */
function markerSources(string $target): string {
    $sources = [];

    if (is_file(dirname(__DIR__, 5).'/'.$target)) {
        $sources[] = file_get_contents(dirname(__DIR__, 5).'/'.$target);
    }

    foreach (shippedModulePaths() as $module_path) {
        if (is_file("{$module_path}/files/{$target}")) {
            $sources[] = file_get_contents("{$module_path}/files/{$target}");
        }

        foreach (Finder::create()->files()->in($module_path)->name('*.php') as $file) {
            $sources[] = file_get_contents($file->getPathname());
        }
    }

    return implode("\n", $sources);
}

it('loads every shipped manifest with all of its variants', function (string $module_path): void {
    $manifest = ModuleManifest::load($module_path, allVariantsOf($module_path));

    expect($manifest->name)->toBe(basename($module_path));
})->with(fn (): array => shippedModulePaths());

it('only injects fragments into markers that exist', function (string $module_path): void {
    $manifest = ModuleManifest::load($module_path, allVariantsOf($module_path));

    expect($manifest->name)->toBe(basename($module_path));

    foreach ($manifest->fragments as $target => $markers) {
        foreach (array_keys($markers) as $marker) {
            expect(markerSources((string) $target))->toContain("@kit:{$marker}");
        }
    }
})->with(fn (): array => shippedModulePaths());
