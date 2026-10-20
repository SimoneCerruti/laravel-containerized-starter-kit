<?php

declare(strict_types=1);

use App\Console\Commands\StarterKit\KitAnswers;
use App\Console\Commands\StarterKit\KitInstaller;
use Illuminate\Support\Facades\Process;

use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\fileperms;
use function Safe\json_decode;
use function Safe\json_encode;

require_once __DIR__.'/ProjectFixture.php';

beforeEach(function (): void {
    Process::fake();
});

describe('install', function (): void {
    test('copies the files of the active modules, replacing placeholders in paths and contents and keeping permissions', function (): void {
        $project = createProjectFixture();

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'My App', 'port' => 9001, 'panel_id' => 'back-office', 'app_type' => 'site']));

        expect(file_get_contents($project.'/README.md'))->toBe('# My App')
            ->and(file_get_contents($project.'/app/BackOffice/Marker.txt'))->toBe('port 9001')
            ->and(fileperms($project.'/bin/run.sh') & 0777)->toBe(0755)
            ->and(file_get_contents($project.'/image.bin'))->toBe("\0__KIT_APP_NAME__");
    });

    test('injects the fragments, strips the leftover markers and applies replacements', function (): void {
        $project = createProjectFixture(['config/app.txt' => 'driver=old']);
        writeModuleManifest($project, 'core', <<<'PHP'
[
    'fragments' => ['app/Models/User.php' => ['user-traits' => 'use HasRoles; // __KIT_APP_SLUG__']],
    'replace' => ['config/app.txt' => ['old' => 'new']],
]
PHP);

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'My App', 'app_type' => 'api']));

        expect(file_get_contents($project.'/app/Models/User.php'))->toBe("<?php\nclass User {\n    use Notifiable;\n    use HasRoles; // my-app\n}\n")
            ->and(file_get_contents($project.'/config/app.txt'))->toBe('driver=new');
    });

    test('merges the variants of the answers into the module manifest', function (): void {
        $project = createProjectFixture();
        $commands = fakeProcessesRecordingCommands();
        writeModuleManifest($project, 'socialite', "['require' => ['base/pkg' => '^1'], 'variants' => ['google' => ['require' => ['google/pkg' => '^2']], 'github' => []]]");

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api', 'socialite_providers' => ['google']]));

        expect($commands->getArrayCopy())->toContain('composer require base/pkg:^1 google/pkg:^2 --no-interaction --no-scripts');
    });

    test('resolves placeholders in the path of a patched file', function (): void {
        $project = createProjectFixture(['app/Providers/AdminPanelProvider.php' => "<?php\n// @kit:panel\n"]);
        fakeProcessesRecordingCommands();
        writeModuleManifest($project, 'core', "['fragments' => ['app/Providers/__KIT_PANEL_CLASS__PanelProvider.php' => ['panel' => '// patched']]]");

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api', 'panel_id' => 'admin']));

        expect(file_get_contents($project.'/app/Providers/AdminPanelProvider.php'))->toContain('// patched');
    });

    test('appends composer scripts without dropping the existing ones', function (): void {
        $project = createProjectFixture();
        fakeProcessesRecordingCommands();
        writeModuleManifest($project, 'core', "['json' => ['composer' => ['scripts.post-autoload-dump' => ['@php artisan filament:upgrade']]]]");
        file_put_contents($project.'/composer.json', json_encode(['scripts' => ['post-autoload-dump' => ['@php artisan package:discover']]]));

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api']));

        $composer = json_decode((string) file_get_contents($project.'/composer.json'), true);

        expect($composer['scripts']['post-autoload-dump'])->toBe(['@php artisan package:discover', '@php artisan filament:upgrade']);
    });

    test('removes the files and packages of the modules that are not selected', function (): void {
        $project = createProjectFixture([
            '.ai/rules/filament.md' => 'rule',
            '.ai/guidelines/dir/inner.md' => 'guide',
        ]);
        $commands = fakeProcessesRecordingCommands();
        writeModuleManifest($project, 'filament', "['when_disabled' => ['remove_files' => ['.ai/rules/filament.md', '.ai/guidelines/dir'], 'remove_packages' => ['old/package', 'old/dev-package', 'not/installed']]]");

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api']));

        expect(file_exists($project.'/.ai/rules/filament.md'))->toBeFalse()
            ->and(is_dir($project.'/.ai/guidelines/dir'))->toBeFalse()
            ->and($commands->getArrayCopy())->toContain('composer remove old/package --no-interaction --no-scripts')
            ->and($commands->getArrayCopy())->toContain('composer remove old/dev-package --dev --no-interaction --no-scripts')
            ->and(implode("\n", $commands->getArrayCopy()))->not->toContain('not/installed');
    });

    test('takes the text of a module that is not selected out of the shared files', function (): void {
        $project = createProjectFixture(['.mcp.json' => "{\"a\": 1,\n\"extra\": 2}"]);
        fakeProcessesRecordingCommands();
        writeModuleManifest($project, 'extra', "['when_disabled' => ['replace' => ['.mcp.json' => [\",\\n\\\"extra\\\": 2\" => ''], 'missing.txt' => ['x' => 'y']]]]");

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api']));

        expect(file_get_contents($project.'/.mcp.json'))->toBe('{"a": 1}');
    });

    test('keeps the files of the modules that are selected', function (): void {
        $project = createProjectFixture(['.ai/rules/filament.md' => '---']);
        writeModuleManifest($project, 'filament', "['when_disabled' => ['remove_files' => ['.ai/rules/filament.md']]]");

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'A']));

        expect(file_exists($project.'/.ai/rules/filament.md'))->toBeTrue();
    });

    test('writes the env entries to both files and the secrets only to .env', function (): void {
        $project = createProjectFixture();
        writeModuleManifest($project, 'core', "['env' => ['APP_THING' => '__KIT_APP_SLUG__'], 'env_local' => ['API_KEY' => '__KIT_CONTEXT7_API_KEY__']]");

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'My App', 'app_type' => 'api', 'context7_key' => 'secret']));

        expect(file_get_contents($project.'/.env'))->toBe("APP_NAME=Laravel\nAPP_THING=my-app\nAPI_KEY=secret\n")
            ->and(file_get_contents($project.'/.env.example'))->toBe("APP_NAME=My App\nAPP_THING=my-app\n");
    });

    test('rebuilds the AI rules index from the rule files that are left', function (): void {
        $project = createProjectFixture(['.ai/rules/a.md' => "---\npaths:\n    - app/**\n---\n", '.ai/rules/index.md' => 'stale']);

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api']));

        expect(file_get_contents($project.'/.ai/rules/index.md'))->toContain('| app/** | .ai/rules/a.md |');
    });

    test('configures composer and npm, then installs the packages without running scripts', function (): void {
        $project = createProjectFixture();
        $commands = fakeProcessesRecordingCommands();
        writeModuleManifest($project, 'core', <<<'PHP'
[
    'require' => ['vendor/one' => '^1.0'],
    'require-dev' => ['vendor/two' => '^2.0'],
    'artisan' => ['php artisan vendor:publish --tag=one'],
    'json' => ['composer' => ['extra.thing' => '__KIT_APP_SLUG__'], 'npm' => ['scripts.x' => 'y', 'list' => ['z']]],
]
PHP);

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'My App', 'app_type' => 'api', 'vendor' => 'acme', 'license' => 'proprietary']));

        expect($commands->getArrayCopy())->toBe([
            'composer config name acme/my-app',
            'composer config description My App',
            'composer config license proprietary',
            'composer config keywords laravel',
            'composer config scripts.setup --json ["composer install","@php artisan migrate --force"]',
            'npm pkg set name=my-app private=true',
            'composer config extra.thing my-app',
            'npm pkg set scripts.x=y',
            'npm pkg set --json list=["z"]',
            'composer require vendor/one:^1.0 --no-interaction --no-scripts',
            'composer require vendor/two:^2.0 --dev --no-interaction --no-scripts',
            'composer dump-autoload',
            'php artisan vendor:publish --tag=one',
            'vendor/bin/pint --parallel',
        ]);
    });

    test('saves the answers without the secrets and removes the kit', function (): void {
        $project = createProjectFixture();

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api', 'gh_token' => 'ghp_secret', 'context7_key' => 'c7']));

        $saved = json_decode((string) file_get_contents($project.'/starter-kit.json'), true);

        expect($saved['app_name'])->toBe('A')
            ->and($saved)->not->toHaveKeys(['gh_token', 'context7_key'])
            ->and(file_exists($project.'/.starterkit'))->toBeFalse()
            ->and(file_exists($project.'/app/Console/Commands/InstallStarterKitCommand.php'))->toBeFalse()
            ->and(file_exists($project.'/app/Console/Commands/StarterKit'))->toBeFalse()
            ->and(file_exists($project.'/tests/App/Console/Commands/StarterKit'))->toBeFalse()
            ->and(file_exists($project.'/tests/App/Console/Commands/InstallStarterKitCommandTest.php'))->toBeFalse();
    });

    test('never touches vendor or the kit own classes', function (): void {
        $project = createProjectFixture();
        $kit_class = 'app/Console/Commands/StarterKit/KitInstaller.php';

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api']));

        expect(file_get_contents($project.'/vendor/pkg/file.php'))->toBe('__KIT_APP_NAME__')
            ->and(file_exists($project.'/'.$kit_class))->toBeFalse();
    });

    test('returns the placeholders that had no value', function (): void {
        $project = createProjectFixture(['config/x.php' => '__KIT_UNKNOWN__']);

        $unresolved = (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api']));

        expect($unresolved)->toBe(['__KIT_UNKNOWN__']);
    });

    test('logs each phase', function (): void {
        $project = createProjectFixture();
        $messages = [];

        (new KitInstaller($project, function (string $message) use (&$messages): void {
            $messages[] = $message;
        }))->install(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api']));

        expect($messages)->toContain('Copying module files', 'Installing packages');
    });

    test('fails before changing anything when a module is missing', function (): void {
        $project = createProjectFixture();

        expect(fn () => (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api', 'modules' => ['money', 'tags']])))
            ->toThrow(RuntimeException::class, 'Missing starter kit modules: money, tags.')
            ->and(file_get_contents($project.'/README.md'))->toBe('kit readme');
    });

    test('fails when a fragment targets a file that does not exist', function (): void {
        $project = createProjectFixture();
        writeModuleManifest($project, 'core', "['fragments' => ['missing/File.php' => ['x' => 'y']]]");

        expect(fn () => (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api'])))
            ->toThrow(RuntimeException::class, 'Cannot patch [missing/File.php]: file not found.');
    });

    test('fails with the output of a command that fails', function (): void {
        $project = createProjectFixture();
        Process::fake(['*' => Process::result(output: 'out', errorOutput: 'boom', exitCode: 1)]);

        expect(fn () => (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api'])))
            ->toThrow(RuntimeException::class, 'Command failed: composer config name simonecerruti/a');
    });
});
