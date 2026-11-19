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

    test('composes SETUP.md from the active modules and their variants when asked', function (): void {
        $project = createProjectFixture([
            '.starterkit/core/SETUP.md' => '## Core for __KIT_APP_SLUG__',
            '.starterkit/socialite/SETUP.md' => '## Social',
            '.starterkit/socialite/SETUP.google.md' => '### Google',
            '.starterkit/socialite/SETUP.github.md' => '### GitHub',
            '.starterkit/backup/SETUP.md' => '## Backup',
        ]);
        writeModuleManifest($project, 'socialite', "['variants' => ['google' => [], 'github' => []]]");
        writeModuleManifest($project, 'backup', '[]');

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'My App', 'app_type' => 'api', 'socialite_providers' => ['google'], 'setup_guide' => true]));

        expect(file_get_contents($project.'/SETUP.md'))->toContain('# My App: setup outside the app')
            ->toContain("## Core for my-app\n\n## Social\n\n### Google\n")
            ->not->toContain('GitHub')
            ->not->toContain('## Backup');
    });

    test('does not write SETUP.md unless asked', function (): void {
        $project = createProjectFixture(['.starterkit/core/SETUP.md' => '## Core']);

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'My App', 'app_type' => 'api']));

        expect($project.'/SETUP.md')->not->toBeFile();
    });

    test('merges the variants of the answers into the module manifest', function (): void {
        $project = createProjectFixture();
        $commands = fakeProcessesRecordingCommands();
        writeModuleManifest($project, 'socialite', "['require' => ['base/pkg' => '^1'], 'variants' => ['google' => ['require' => ['google/pkg' => '^2']], 'github' => []]]");

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api', 'socialite_providers' => ['google']]));

        expect($commands->getArrayCopy())->toContain('composer require base/pkg:^1 google/pkg:^2 --no-interaction --no-scripts --with-all-dependencies');
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

    test('merges the JSON entries of the modules: a list is appended to the one there, anything else is set', function (): void {
        $project = createProjectFixture(['.mcp.json' => '{"servers": {"a": 1}, "allow": ["x"]}']);
        fakeProcessesRecordingCommands();
        writeModuleManifest($project, 'extra', "['json' => ['files' => ['.mcp.json' => ['servers.extra' => ['command' => 'extra'], 'allow' => ['x', 'extra__*'], 'hooks.Submit' => [['type' => 'command']]]]]]");
        $answers = KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api', 'modules' => ['extra']]);

        (new KitInstaller($project))->install($answers);

        expect(json_decode(file_get_contents($project.'/.mcp.json'), true))->toBe([
            'servers' => ['a' => 1, 'extra' => ['command' => 'extra']],
            'allow' => ['x', 'extra__*'],
            'hooks' => ['Submit' => [['type' => 'command']]],
        ]);
    });

    test('keeps the empty JSON objects of a file it merges into', function (): void {
        $project = createProjectFixture(['.mcp.json' => '{"env": {}, "servers": {}}']);
        fakeProcessesRecordingCommands();
        writeModuleManifest($project, 'extra', "['json' => ['files' => ['.mcp.json' => ['servers.extra' => ['command' => 'extra']]]]]");

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api', 'modules' => ['extra']]));

        expect(json_decode(file_get_contents($project.'/.mcp.json'), true))->toBe(['env' => [], 'servers' => ['extra' => ['command' => 'extra']]])
            ->and(file_get_contents($project.'/.mcp.json'))->toContain('"env": {}');
    });

    test('reports the placeholders left without a value in SETUP.md', function (): void {
        $project = createProjectFixture(['.starterkit/core/SETUP.md' => '## Core __KIT_NOT_A_THING__']);

        $unresolved = (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api', 'setup_guide' => true]));

        expect($unresolved)->toBe(['__KIT_NOT_A_THING__']);
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

    test('quotes the env values that contain spaces', function (): void {
        $project = createProjectFixture(['.env.example' => "APP_NAME=Laravel\nAPP_URL=http://localhost\n"]);
        writeModuleManifest($project, 'core', "['env' => ['APP_NAME' => '__KIT_APP_NAME__', 'APP_URL' => 'http://localhost:__KIT_APP_PORT__']]");

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'My App', 'port' => 84, 'app_type' => 'api']));

        expect(file_get_contents($project.'/.env.example'))->toBe("APP_NAME=\"My App\"\nAPP_URL=http://localhost:84\n");
    });

    test('runs the commands without the env values the installer booted with', function (): void {
        $project = createProjectFixture();

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api']));

        Process::assertRan(fn ($process): bool => ($process->environment['APP_NAME'] ?? null) === false);
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
            'npm pkg set name=my-app private=true',
            'npm pkg set license=UNLICENSED',
            'composer config extra.thing my-app',
            'npm pkg set scripts.x=y',
            'npm pkg set --json list=["z"]',
            'composer require vendor/one:^1.0 --no-interaction --no-scripts --with-all-dependencies',
            'composer require vendor/two:^2.0 --dev --no-interaction --no-scripts --with-all-dependencies',
            'composer dump-autoload',
            'php artisan vendor:publish --tag=one',
            'vendor/bin/pint --parallel',
        ]);

        $composer = json_decode((string) file_get_contents($project.'/composer.json'), true);

        expect($composer['scripts']['setup'])->toBe(['composer install', '@php artisan migrate --force'])
            ->and($composer['extra']['laravel'] ?? [])->not->toHaveKey('installer');
    });

    test('sets the repository links and the author when they are given', function (): void {
        $project = createProjectFixture();
        $commands = fakeProcessesRecordingCommands();

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'My App', 'app_type' => 'api', 'gh_repository' => 'acme/my-app', 'author_name' => 'Jane Doe', 'author_email' => 'jane@acme.test']));

        expect($commands->getArrayCopy())->toContain('composer config homepage https://github.com/acme/my-app')
            ->toContain('npm pkg set homepage=https://github.com/acme/my-app repository=https://github.com/acme/my-app')
            ->toContain('npm pkg set author=Jane Doe <jane@acme.test>')
            ->toContain('npm pkg set license=MIT');

        $composer = json_decode((string) file_get_contents($project.'/composer.json'), true);

        expect($composer['support'])->toBe([
            'issues' => 'https://github.com/acme/my-app/issues',
            'source' => 'https://github.com/acme/my-app/tree/main',
        ])->and($composer['authors'])->toBe([['name' => 'Jane Doe', 'email' => 'jane@acme.test']]);
    });

    test('leaves the repository links and the authors alone when they are not given', function (): void {
        $project = createProjectFixture();
        $commands = fakeProcessesRecordingCommands();

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'My App', 'app_type' => 'api']));

        expect(implode("\n", $commands->getArrayCopy()))->not->toContain('homepage')->not->toContain('authors')->not->toContain('author=');
    });

    test('writes the LICENSE of the chosen license with the year and the holder', function (): void {
        $this->travelTo(now()->setYear(2031));
        $project = createProjectFixture([
            '.starterkit/core/licenses/MIT.txt' => 'MIT (c) __KIT_YEAR__ __KIT_LICENSE_HOLDER__',
            '.starterkit/core/licenses/proprietary.txt' => 'Proprietary (c) __KIT_YEAR__ __KIT_LICENSE_HOLDER__',
        ]);

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'My App', 'app_type' => 'api', 'vendor' => 'acme']));

        expect(file_get_contents($project.'/LICENSE'))->toBe('MIT (c) 2031 acme');

        $other = createProjectFixture(['.starterkit/core/licenses/proprietary.txt' => 'Proprietary (c) __KIT_YEAR__ __KIT_LICENSE_HOLDER__']);
        (new KitInstaller($other))->install(KitAnswers::fromArray(['app_name' => 'My App', 'app_type' => 'api', 'license' => 'proprietary', 'author_name' => 'Jane Doe']));

        expect(file_get_contents($other.'/LICENSE'))->toBe('Proprietary (c) 2031 Jane Doe');
    });

    test('does not write a LICENSE when there is no text for the license', function (): void {
        $project = createProjectFixture();

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'My App', 'app_type' => 'api']));

        expect($project.'/LICENSE')->not->toBeFile();
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

describe('ai-tooling module', function (): void {
    test('clears the WSL variables before boost:update so .mcp.json keeps the plain php command', function (): void {
        $project = createProjectFixture(['composer.json' => '{}', 'docker-compose.local.yml' => "environment:\n    # @kit:compose-environment\n"]);
        copyShippedModuleToProject($project, 'ai-tooling');

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api', 'ai_tooling' => true]));

        $composer = json_decode((string) file_get_contents($project.'/composer.json'), true);
        $script = $composer['scripts']['ai:update'];

        expect($script)->toContain('@putenv WSL_DISTRO_NAME=', '@putenv IS_WSL=')
            ->and(array_search('@putenv IS_WSL=', $script, true))->toBeLessThan(array_search('@php artisan boost:update --ansi', $script, true))
            ->and($composer['scripts']['post-update-cmd'])->toContain('@composer ai:update');
    });
});

describe('cloudflare module', function (): void {
    function installCloudflareFixture(bool $cloudflare): string {
        $project = createProjectFixture(['docker/Dockerfile' => "FROM base\n# @kit:web-production\n"]);
        copyShippedModuleToProject($project, 'cloudflare');
        copyRepositoryFileToProject($project, 'docker/scripts/entrypoint.sh');
        copyRepositoryFileToProject($project, 'bootstrap/app.php');

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'My App', 'app_type' => 'api', 'cloudflare' => $cloudflare]));

        return $project;
    }

    test('replaces the default of REAL_IP_HEADER instead of adding a second line', function (): void {
        $entrypoint = file_get_contents(installCloudflareFixture(true).'/docker/scripts/entrypoint.sh');

        expect(substr_count($entrypoint, 'REAL_IP_HEADER='))->toBe(1)
            ->and($entrypoint)->toContain('export REAL_IP_HEADER=${REAL_IP_HEADER:-CF-Connecting-IP}')
            ->not->toContain('X-Forwarded-For')
            ->toContain('cloudflare-ips.sh');
    });

    test('keeps the generic default when the module is off', function (): void {
        $entrypoint = file_get_contents(installCloudflareFixture(false).'/docker/scripts/entrypoint.sh');

        expect(substr_count($entrypoint, 'REAL_IP_HEADER='))->toBe(1)
            ->and($entrypoint)->toContain('export REAL_IP_HEADER=${REAL_IP_HEADER:-X-Forwarded-For}')
            ->not->toContain('CF-Connecting-IP')
            ->not->toContain('cloudflare-ips.sh');
    });

    test('trusts only the Docker proxy ranges instead of every sender', function (): void {
        $bootstrap = file_get_contents(installCloudflareFixture(true).'/bootstrap/app.php');

        $nginx_ranges = file_get_contents(dirname(__DIR__, 5).'/docker/configs/nginx-real-ip.conf');

        expect($bootstrap)->toContain("trustProxies(at: ['172.16.0.0/12', '10.0.0.0/8'])")
            ->not->toContain("at: '*'")
            ->and($nginx_ranges)->toContain('set_real_ip_from 172.16.0.0/12;')
            ->toContain('set_real_ip_from 10.0.0.0/8;');
    });

    test('does not trust any proxy when the module is off', function (): void {
        expect(file_get_contents(installCloudflareFixture(false).'/bootstrap/app.php'))->not->toContain('trustProxies');
    });
});

describe('mfa-reset module', function (): void {
    /**
     * @param  list<string>  $modules
     */
    function installMfaResetFixture(array $modules): string {
        $project = createProjectFixture();

        foreach (['mfa-reset', 'login-audit'] as $module) {
            copyShippedModuleToProject($project, $module);
        }

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'My App', 'app_type' => 'api', 'modules' => $modules]));

        return $project;
    }

    test('logs the reset and adds its event when login-audit is installed too', function (): void {
        $project = installMfaResetFixture(['mfa-reset', 'login-audit']);

        expect(file_get_contents($project.'/app/Enums/ActivityEvent.php'))->toContain("case MFA_RESET = 'mfa_reset';")
            ->not->toContain('@kit:')
            ->and(file_get_contents($project.'/app/Actions/ResetUserMultiFactorAuthentication.php'))
            ->toContain('use App\Enums\ActivityEvent;')
            ->toContain('->event(ActivityEvent::MFA_RESET->value)')
            ->and(file_get_contents($project.'/tests/App/Enums/ActivityEventTest.php'))->toContain("'mfa_reset',")
            ->and(file_get_contents($project.'/tests/App/Actions/ResetUserMultiFactorAuthenticationTest.php'))->toContain('records the reset in the activity log');
    });

    test('resets without logging anything when login-audit is not installed', function (): void {
        $project = installMfaResetFixture(['mfa-reset']);

        expect(file_get_contents($project.'/app/Actions/ResetUserMultiFactorAuthentication.php'))
            ->toContain('DB::transaction')
            ->not->toContain('ActivityEvent')
            ->not->toContain('activity(')
            ->not->toContain('@kit:')
            ->and($project.'/app/Enums/ActivityEvent.php')->not->toBeFile()
            ->and(file_get_contents($project.'/tests/App/Actions/ResetUserMultiFactorAuthenticationTest.php'))->not->toContain('activity log');
    });
});

describe('localized-routes module', function (): void {
    function installLocalizedRoutesFixture(bool $localized): string {
        $project = createProjectFixture();
        copyShippedModuleToProject($project, 'localized-routes');

        foreach (['routes/web.php', 'bootstrap/app.php', 'tests/Pest.php'] as $file) {
            copyRepositoryFileToProject($project, $file);
        }

        (new KitInstaller($project))->install(KitAnswers::fromArray(['app_name' => 'My App', 'app_type' => 'site', 'modules' => $localized ? ['localized-routes'] : []]));

        return $project;
    }

    test('wraps the public routes in the localized group and names the home route', function (): void {
        $project = installLocalizedRoutesFixture(true);
        $routes = file_get_contents($project.'/routes/web.php');

        expect($routes)->toContain('use Mcamara\LaravelLocalization\Facades\LaravelLocalization;')
            ->toContain('Route::prefix(LaravelLocalization::setLocale())')
            ->toContain("->middleware(['localeSessionRedirect', 'localizationRedirect', 'localize'])")
            ->toContain("->name('home')")
            ->and(file_get_contents($project.'/bootstrap/app.php'))->toContain("'localize' => LaravelLocalizationRoutes::class")
            ->and(file_get_contents($project.'/tests/Pest.php'))->toContain('function getLocalizedPublicRouteNames(): array')
            ->toContain('function loadRoutesForLocale(string $locale): void')
            ->and($project.'/tests/Routes/LocalizedRoutesTest.php')->toBeFile()
            ->and($project.'/config/laravellocalization.php')->toBeFile();
    });

    test('leaves the routes alone when the module is off', function (): void {
        $project = installLocalizedRoutesFixture(false);

        expect(file_get_contents($project.'/routes/web.php'))->not->toContain('LaravelLocalization')
            ->and($project.'/tests/Routes/LocalizedRoutesTest.php')->not->toBeFile();
    });
});
