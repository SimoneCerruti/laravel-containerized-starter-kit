<?php

declare(strict_types=1);

namespace App\Console\Commands\StarterKit;

use Closure;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Symfony\Component\Finder\Finder;

use function Safe\chmod;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\glob;
use function Safe\json_decode;
use function Safe\json_encode;
use function Safe\mkdir;
use function Safe\rmdir;
use function Safe\scandir;
use function Safe\unlink;

/**
 * Applies the answers to a project: copies the module files, injects the fragments, replaces the
 * placeholders, writes the env files, installs the packages and finally removes the kit itself.
 */
final class KitInstaller {
    private const KIT_DIRECTORY = '.starterkit';

    /** @var list<string> */
    private const SELF_REMOVED_PATHS = [
        self::KIT_DIRECTORY,
        'app/Console/Commands/InstallStarterKitCommand.php',
        'app/Console/Commands/StarterKit',
        'tests/App/Console/Commands/StarterKit',
        'tests/App/Console/Commands/InstallStarterKitCommandTest.php',
    ];

    /** @var list<string> never scanned for placeholders or markers */
    private const UNSCANNED_PATHS = [
        'vendor',
        'node_modules',
        'storage',
        'public/build',
        'composer.lock',
        'package-lock.json',
        self::KIT_DIRECTORY,
        'app/Console/Commands/StarterKit',
        'tests/App/Console/Commands/StarterKit',
        'tests/App/Console/Commands/InstallStarterKitCommandTest.php',
    ];

    /**
     * @param  Closure(string): void|null  $log
     */
    public function __construct(
        private readonly string $base_path,
        private readonly ?Closure $log = null,
    ) {}

    /**
     * @return list<string> placeholders that no value was provided for
     */
    public function install(KitAnswers $answers): array {
        $active = $answers->activeModules();
        $manifests = $this->loadManifests($active, $answers);
        $replacer = new PlaceholderReplacer($answers->placeholders());

        $this->say('Copying module files');

        foreach ($active as $module) {
            $this->copyFiles($module, $replacer);
        }

        $this->say('Injecting fragments');

        foreach ($manifests as $manifest) {
            $this->applyManifest($manifest, $replacer);
        }

        $this->removeDisabledModules($active);
        $unresolved = $this->replacePlaceholdersInProject($replacer);
        $this->stripMarkers();
        $this->writeEnvFiles($manifests, $replacer);
        $this->rebuildRulesIndex();

        $this->say('Configuring composer and npm');
        $this->configureProjectIdentity($answers);

        foreach ($manifests as $manifest) {
            $this->applyJsonConfig($manifest, $replacer);
        }

        $this->say('Installing packages');
        $this->installPackages($manifests);

        $this->say('Running post-install commands');

        foreach ($manifests as $manifest) {
            foreach ($manifest->artisan as $command) {
                $this->run($command);
            }
        }

        if ($answers->setup_guide) {
            $this->writeSetupGuide($active, $answers, $replacer);
        }

        $this->saveAnswers($answers);
        $this->run('vendor/bin/pint --parallel');
        $this->removeKit();

        return $unresolved;
    }

    /**
     * @param  list<string>  $active
     * @return list<ModuleManifest>
     */
    private function loadManifests(array $active, KitAnswers $answers): array {
        $missing = array_values(array_filter($active, fn (string $module): bool => ! is_dir($this->modulePath($module))));

        if ($missing !== []) {
            throw new RuntimeException('Missing starter kit modules: '.implode(', ', $missing).'.');
        }

        return array_map(
            fn (string $module): ModuleManifest => ModuleManifest::load($this->modulePath($module), $answers->variants()[$module] ?? []),
            $active,
        );
    }

    private function copyFiles(string $module, PlaceholderReplacer $replacer): void {
        $source_root = $this->modulePath($module).'/files';

        if (! is_dir($source_root)) {
            return;
        }

        foreach (Finder::create()->files()->ignoreDotFiles(false)->in($source_root) as $file) {
            $destination = $this->base_path.'/'.$replacer->replace($file->getRelativePathname());
            $contents = $file->getContents();

            if (! is_dir(\dirname($destination))) {
                mkdir(\dirname($destination), 0755, true);
            }

            file_put_contents($destination, str_contains($contents, "\0") ? $contents : $replacer->replace($contents));
            chmod($destination, $file->getPerms() & 0777);
        }
    }

    private function applyManifest(ModuleManifest $manifest, PlaceholderReplacer $replacer): void {
        foreach ($manifest->replace as $file => $replacements) {
            $this->updateFile($replacer->replace($file), fn (string $contents): string => strtr($contents, $replacements));
        }

        $patcher = new MarkerPatcher;

        foreach ($manifest->fragments as $file => $markers) {
            $this->updateFile($replacer->replace($file), function (string $contents) use ($markers, $patcher, $replacer): string {
                foreach ($markers as $marker => $code) {
                    $contents = $patcher->inject($contents, $marker, $replacer->replace($code));
                }

                return $contents;
            });
        }
    }

    /**
     * @param  Closure(string): string  $transform
     */
    private function updateFile(string $relative_path, Closure $transform): void {
        $path = $this->base_path.'/'.$relative_path;

        if (! is_file($path)) {
            throw new RuntimeException("Cannot patch [{$relative_path}]: file not found.");
        }

        file_put_contents($path, $transform((string) file_get_contents($path)));
    }

    /**
     * @param  list<string>  $active
     */
    private function removeDisabledModules(array $active): void {
        $kit_path = $this->base_path.'/'.self::KIT_DIRECTORY;

        foreach (glob($kit_path.'/*', GLOB_ONLYDIR) as $module_path) {
            if (\in_array(basename($module_path), $active, true)) {
                continue;
            }

            $manifest = ModuleManifest::load($module_path);

            foreach ($manifest->remove_files as $path) {
                $this->deletePath($this->base_path.'/'.$path);
            }

            foreach ($manifest->replace_when_disabled as $file => $replacements) {
                if (is_file($this->base_path.'/'.$file)) {
                    $this->updateFile($file, fn (string $contents): string => strtr($contents, $replacements));
                }
            }

            $composer = $this->readJson('composer.json');

            foreach (['require' => [], 'require-dev' => ['--dev']] as $section => $flags) {
                $packages = array_values(array_filter($manifest->remove_packages, fn (string $package): bool => isset(((array) ($composer[$section] ?? []))[$package])));

                if ($packages !== []) {
                    $this->run(['composer', 'remove', ...$packages, ...$flags, '--no-interaction', '--no-scripts']);
                }
            }
        }
    }

    /**
     * @return list<string> placeholders left without a value
     */
    private function replacePlaceholdersInProject(PlaceholderReplacer $replacer): array {
        $unresolved = [];

        foreach ($this->scannableFiles() as $path) {
            $contents = (string) file_get_contents($path);

            if (! str_contains($contents, '__KIT_')) {
                continue;
            }

            $unresolved = [...$unresolved, ...$replacer->findUnresolved($contents)];
            file_put_contents($path, $replacer->replace($contents));
        }

        return array_values(array_unique($unresolved));
    }

    private function stripMarkers(): void {
        $patcher = new MarkerPatcher;

        foreach ($this->scannableFiles() as $path) {
            $contents = (string) file_get_contents($path);

            if (str_contains($contents, '@kit:')) {
                file_put_contents($path, $patcher->stripMarkers($contents));
            }
        }
    }

    /**
     * @param  list<ModuleManifest>  $manifests
     */
    private function writeEnvFiles(array $manifests, PlaceholderReplacer $replacer): void {
        $shared = array_merge(...array_map(fn (ModuleManifest $manifest): array => $manifest->env, $manifests));
        $local = array_merge(...array_map(fn (ModuleManifest $manifest): array => $manifest->env_local, $manifests));
        $writer = new EnvWriter;

        foreach (['.env.example' => $shared, '.env' => [...$shared, ...$local]] as $file => $values) {
            $path = $this->base_path.'/'.$file;

            if (is_file($path) && $values !== []) {
                file_put_contents($path, $writer->apply((string) file_get_contents($path), array_map($replacer->replace(...), $values)));
            }
        }
    }

    private function rebuildRulesIndex(): void {
        $rules_directory = $this->base_path.'/.ai/rules';

        if (is_dir($rules_directory)) {
            file_put_contents($rules_directory.'/index.md', (new RulesIndexBuilder)->build($rules_directory));
        }
    }

    private function configureProjectIdentity(KitAnswers $answers): void {
        $this->run(['composer', 'config', 'name', "{$answers->vendor}/{$answers->app_slug}"]);
        $this->run(['composer', 'config', 'description', $answers->app_name]);
        $this->run(['composer', 'config', 'license', $answers->license]);
        $this->run(['composer', 'config', 'keywords', 'laravel']);
        $this->run(['composer', 'config', 'scripts.setup', '--json', json_encode($this->setupScriptWithoutKit(), JSON_THROW_ON_ERROR)]);
        $this->run(['npm', 'pkg', 'set', "name={$answers->app_slug}", 'private=true']);
    }

    /**
     * @return list<string>
     */
    private function setupScriptWithoutKit(): array {
        $composer = $this->readJson('composer.json');
        $steps = (array) ($composer['scripts']['setup'] ?? []);

        return array_values(array_filter($steps, fn (mixed $step): bool => ! str_contains((string) $step, 'starter-kit:install')));
    }

    private function applyJsonConfig(ModuleManifest $manifest, PlaceholderReplacer $replacer): void {
        foreach ($manifest->composer_config as $key => $value) {
            if (str_starts_with($key, 'scripts.') && \is_array($value)) {
                $this->appendComposerScripts(substr($key, \strlen('scripts.')), array_values(array_map(strval(...), $value)));

                continue;
            }

            $this->run(\is_array($value)
                ? ['composer', 'config', $key, '--json', json_encode($value, JSON_THROW_ON_ERROR)]
                : ['composer', 'config', $key, $replacer->replace((string) $value)]);
        }

        foreach ($manifest->npm_config as $key => $value) {
            $this->run(\is_array($value)
                ? ['npm', 'pkg', 'set', '--json', "{$key}=".json_encode($value, JSON_THROW_ON_ERROR)]
                : ['npm', 'pkg', 'set', "{$key}=".$replacer->replace((string) $value)]);
        }
    }

    /**
     * Composer scripts are skipped while requiring: a provider copied by a module may
     * reference a package that is not installed yet, which would break package discovery.
     *
     * @param  list<ModuleManifest>  $manifests
     */
    private function installPackages(array $manifests): void {
        $require = array_merge(...array_map(fn (ModuleManifest $manifest): array => $manifest->require, $manifests));
        $require_dev = array_merge(...array_map(fn (ModuleManifest $manifest): array => $manifest->require_dev, $manifests));

        foreach ([[$require, []], [$require_dev, ['--dev']]] as [$packages, $flags]) {
            if ($packages !== []) {
                $this->run(['composer', 'require', ...array_map(fn (string $package, string $constraint): string => "{$package}:{$constraint}", array_keys($packages), $packages), ...$flags, '--no-interaction', '--no-scripts']);
            }
        }

        $this->run(['composer', 'dump-autoload']);
    }

    /**
     * Composes `SETUP.md` from the `SETUP.md` of every active module (and `SETUP.<variant>.md` of its variants):
     * the steps to do outside the app, kept after the kit is removed.
     *
     * @param  list<string>  $active
     */
    private function writeSetupGuide(array $active, KitAnswers $answers, PlaceholderReplacer $replacer): void {
        $sections = [];

        foreach ($active as $module) {
            foreach (['SETUP.md', ...array_map(fn (string $variant): string => "SETUP.{$variant}.md", $answers->variants()[$module] ?? [])] as $file) {
                $path = $this->modulePath($module).'/'.$file;

                if (is_file($path)) {
                    $sections[] = trim($replacer->replace(file_get_contents($path)));
                }
            }
        }

        if ($sections === []) {
            return;
        }

        $this->say('Writing SETUP.md');
        file_put_contents($this->base_path.'/SETUP.md', "# {$answers->app_name}: setup outside the app\n\nThe steps that cannot be done by the code itself. Follow the ones of the modules you use.\n\n".implode("\n\n", $sections)."\n");
    }

    private function saveAnswers(KitAnswers $answers): void {
        $safe = collect($answers->toArray())->except(['gh_token', 'context7_key'])->all();

        file_put_contents($this->base_path.'/starter-kit.json', json_encode($safe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    }

    private function removeKit(): void {
        foreach (self::SELF_REMOVED_PATHS as $path) {
            $this->deletePath($this->base_path.'/'.$path);
        }
    }

    /**
     * @return list<string>
     */
    private function scannableFiles(): array {
        $files = Finder::create()->files()->ignoreDotFiles(false)->ignoreVCS(true)->in($this->base_path)
            ->notPath(array_map(fn (string $path): string => '#^'.preg_quote($path, '#').'(/|$)#', self::UNSCANNED_PATHS));

        return array_values(array_filter(
            array_map(fn (\SplFileInfo $file): string => $file->getPathname(), iterator_to_array($files, false)),
            fn (string $path): bool => ! str_contains((string) file_get_contents($path, length: 8192), "\0"),
        ));
    }

    /**
     * Adds commands to a composer script event, keeping the ones already there.
     *
     * @param  list<string>  $commands
     */
    private function appendComposerScripts(string $event, array $commands): void {
        $composer = $this->readJson('composer.json');
        $existing = array_map(strval(...), (array) ($composer['scripts'][$event] ?? []));
        $composer['scripts'][$event] = array_values(array_unique([...$existing, ...$commands]));

        file_put_contents($this->base_path.'/composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $relative_path): array {
        $decoded = json_decode((string) file_get_contents($this->base_path.'/'.$relative_path), true, flags: JSON_THROW_ON_ERROR);

        return \is_array($decoded) ? $decoded : [];
    }

    private function deletePath(string $path): void {
        if (is_dir($path) && ! is_link($path)) {
            $entries = array_diff(scandir($path), ['.', '..']);

            foreach ($entries as $entry) {
                $this->deletePath($path.'/'.$entry);
            }

            rmdir($path);

            return;
        }

        if (file_exists($path) || is_link($path)) {
            unlink($path);
        }
    }

    private function modulePath(string $module): string {
        return $this->base_path.'/'.self::KIT_DIRECTORY.'/'.$module;
    }

    /**
     * @param  list<string>|string  $command
     */
    private function run(array|string $command): void {
        $result = Process::path($this->base_path)->timeout(0)->run($command);

        if ($result->failed()) {
            $printable = \is_array($command) ? implode(' ', $command) : $command;

            throw new RuntimeException("Command failed: {$printable}\n".$result->errorOutput().$result->output());
        }
    }

    private function say(string $message): void {
        if ($this->log instanceof Closure) {
            ($this->log)($message);
        }
    }
}
