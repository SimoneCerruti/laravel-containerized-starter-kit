<?php

declare(strict_types=1);

namespace App\Console\Commands\StarterKit;

use InvalidArgumentException;

use function Safe\file_get_contents;

/**
 * The declarative `module.php` of a starter kit module, with the chosen `variants` merged in.
 *
 * Fragment values are either the code itself or the path, relative to the module folder, of a
 * file holding it: the manifest resolves both to the code.
 */
final readonly class ModuleManifest {
    /**
     * @param  array<string, string>  $require
     * @param  array<string, string>  $require_dev
     * @param  array<string, array<string, string>>  $fragments  file => marker => code
     * @param  array<string, array<string, string>>  $replace  file => search => replacement
     * @param  array<string, string>  $env  written to .env and .env.example
     * @param  array<string, string>  $env_local  written to .env only (secrets)
     * @param  list<string>  $artisan  shell commands run after the packages are installed
     * @param  list<string>  $remove_files  removed when the module is not selected
     * @param  list<string>  $remove_packages  removed when the module is not selected
     * @param  array<string, array<string, string>>  $replace_when_disabled  file => search => replacement, applied when the module is not selected
     * @param  array<string, mixed>  $composer_config  `composer config` keys, arrays are passed as JSON
     * @param  array<string, mixed>  $npm_config  `npm pkg set` keys, arrays are passed as JSON
     */
    public function __construct(
        public string $name,
        public array $require = [],
        public array $require_dev = [],
        public array $fragments = [],
        public array $replace = [],
        public array $env = [],
        public array $env_local = [],
        public array $artisan = [],
        public array $remove_files = [],
        public array $remove_packages = [],
        public array $replace_when_disabled = [],
        public array $composer_config = [],
        public array $npm_config = [],
    ) {}

    /**
     * @param  list<string>  $variants
     */
    public static function load(string $module_path, array $variants = []): self {
        $name = basename($module_path);
        $manifest_file = $module_path.'/module.php';
        $definition = is_file($manifest_file) ? require $manifest_file : [];

        if (! \is_array($definition)) {
            throw new InvalidArgumentException("Module [{$name}] manifest must return an array.");
        }

        $available_variants = \is_array($definition['variants'] ?? null) ? $definition['variants'] : [];

        foreach ($variants as $variant) {
            if (! \array_key_exists($variant, $available_variants)) {
                throw new InvalidArgumentException("Module [{$name}] has no variant [{$variant}].");
            }

            $definition = self::merge($definition, (array) $available_variants[$variant]);
        }

        return new self(
            name: $name,
            require: self::stringMap($definition['require'] ?? []),
            require_dev: self::stringMap($definition['require-dev'] ?? []),
            fragments: self::resolveFragments($module_path, (array) ($definition['fragments'] ?? [])),
            replace: self::nestedStringMap($definition['replace'] ?? []),
            env: self::stringMap($definition['env'] ?? []),
            env_local: self::stringMap($definition['env_local'] ?? []),
            artisan: self::stringList($definition['artisan'] ?? []),
            remove_files: self::stringList(self::path($definition, 'when_disabled', 'remove_files')),
            remove_packages: self::stringList(self::path($definition, 'when_disabled', 'remove_packages')),
            replace_when_disabled: self::nestedStringMap(self::path($definition, 'when_disabled', 'replace')),
            composer_config: (array) self::path($definition, 'json', 'composer'),
            npm_config: (array) self::path($definition, 'json', 'npm'),
        );
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function merge(array $base, array $extra): array {
        foreach ($extra as $key => $value) {
            $existing = $base[$key] ?? null;

            $base[$key] = match (true) {
                $key === 'fragments' => self::mergeFragments((array) $existing, (array) $value),
                $key === 'artisan' => [...(array) $existing, ...(array) $value],
                \is_array($existing) && \is_array($value) => [...$existing, ...$value],
                default => $value,
            };
        }

        return $base;
    }

    /**
     * @param  array<string, array<string, string>>  $base
     * @param  array<string, array<string, string>>  $extra
     * @return array<string, array<string, list<string>|string>>
     */
    private static function mergeFragments(array $base, array $extra): array {
        foreach ($extra as $file => $markers) {
            foreach ($markers as $marker => $code) {
                $existing = $base[$file][$marker] ?? null;
                $base[$file][$marker] = $existing === null ? $code : [...(array) $existing, ...(array) $code];
            }
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $files
     * @return array<string, array<string, string>>
     */
    private static function resolveFragments(string $module_path, array $files): array {
        $resolved = [];

        foreach ($files as $file => $markers) {
            foreach ((array) $markers as $marker => $codes) {
                $resolved[(string) $file][(string) $marker] = collect((array) $codes)
                    ->map(fn (mixed $code): string => self::resolveCode($module_path, (string) $code))
                    ->implode("\n");
            }
        }

        return $resolved;
    }

    private static function resolveCode(string $module_path, string $code): string {
        $is_reference = ! str_contains($code, "\n") && is_file($module_path.'/'.$code);

        return rtrim($is_reference ? (string) file_get_contents($module_path.'/'.$code) : $code, "\n");
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private static function path(array $definition, string $group, string $key): mixed {
        $section = $definition[$group] ?? [];

        return \is_array($section) ? ($section[$key] ?? []) : [];
    }

    /**
     * @return array<string, string>
     */
    private static function stringMap(mixed $value): array {
        return collect((array) $value)->map(fn (mixed $item): string => (string) $item)->all();
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function nestedStringMap(mixed $value): array {
        return collect((array) $value)->map(fn (mixed $item): array => self::stringMap($item))->all();
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array {
        return array_values(array_map(strval(...), (array) $value));
    }
}
