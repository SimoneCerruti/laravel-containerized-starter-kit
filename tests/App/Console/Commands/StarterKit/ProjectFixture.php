<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

use function Safe\chmod;
use function Safe\file_put_contents;
use function Safe\json_encode;
use function Safe\mkdir;

/**
 * @param  array<string, string>  $files  path => contents, relative to the project
 */
function createProjectFixture(array $files = []): string {
    $project = sys_get_temp_dir().'/kit_project_'.uniqid();

    $defaults = [
        'composer.json' => json_encode(['require' => ['laravel/framework' => '^13.0', 'old/package' => '^1.0'], 'require-dev' => ['old/dev-package' => '^1.0'], 'scripts' => ['setup' => ['composer install', '@php artisan migrate --force', '@php artisan starter-kit:install']]]),
        '.env' => "APP_NAME=Laravel\n",
        '.env.example' => "APP_NAME=__KIT_APP_NAME__\n# @kit:env\n",
        'app/Models/User.php' => "<?php\nclass User {\n    use Notifiable;\n    // @kit:user-traits\n}\n",
        'README.md' => 'kit readme',
        '.starterkit/core/files/README.md' => '# __KIT_APP_NAME__',
        '.starterkit/core/files/app/__KIT_PANEL_CLASS__/Marker.txt' => 'port __KIT_APP_PORT__',
        '.starterkit/core/files/bin/run.sh' => '#!/bin/sh',
        '.starterkit/core/files/image.bin' => "\0__KIT_APP_NAME__",
        'app/Console/Commands/InstallStarterKitCommand.php' => 'command',
        'app/Console/Commands/StarterKit/KitInstaller.php' => '// @kit:keep-me',
        'tests/App/Console/Commands/InstallStarterKitCommandTest.php' => 'test',
        'vendor/pkg/file.php' => '__KIT_APP_NAME__',
    ];

    foreach ([...$defaults, ...$files] as $path => $contents) {
        if (! is_dir(dirname($project.'/'.$path))) {
            mkdir(dirname($project.'/'.$path), 0755, true);
        }

        file_put_contents($project.'/'.$path, $contents);
    }

    chmod($project.'/.starterkit/core/files/bin/run.sh', 0755);

    return $project;
}

function writeModuleManifest(string $project, string $module, string $manifest): void {
    $directory = $project.'/.starterkit/'.$module;

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    file_put_contents($directory.'/module.php', "<?php\n\nreturn {$manifest};\n");
}

/**
 * @return ArrayObject<int, string>
 */
function fakeProcessesRecordingCommands(): ArrayObject {
    $commands = new ArrayObject;

    Process::fake(function (PendingProcess $process) use ($commands) {
        $commands->append(is_array($process->command) ? implode(' ', $process->command) : $process->command);

        return Process::result();
    });

    return $commands;
}
