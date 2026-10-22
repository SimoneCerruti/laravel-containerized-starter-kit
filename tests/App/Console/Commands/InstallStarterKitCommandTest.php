<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

use function Pest\Laravel\artisan;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\json_decode;
use function Safe\json_encode;
use function Safe\mkdir;

require_once __DIR__.'/StarterKit/ProjectFixture.php';

beforeEach(function (): void {
    Process::fake();
});

describe('with an answers file', function (): void {
    test('installs the kit into the given project and exits successfully', function (): void {
        $project = createProjectFixture();
        $answers_file = $project.'/answers.json';
        file_put_contents($answers_file, json_encode(['app_name' => 'My App', 'app_type' => 'api']));

        artisan('starter-kit:install', ['--answers' => $answers_file, '--base-path' => $project])
            ->expectsOutputToContain('Starter kit installed and removed.')
            ->assertSuccessful();

        expect(file_get_contents($project.'/README.md'))->toBe('# My App');
    });

    test('warns about the placeholders left without a value', function (): void {
        $project = createProjectFixture(['config/x.php' => '__KIT_UNKNOWN__']);
        $answers_file = $project.'/answers.json';
        file_put_contents($answers_file, json_encode(['app_name' => 'A', 'app_type' => 'api']));

        artisan('starter-kit:install', ['--answers' => $answers_file, '--base-path' => $project])
            ->expectsOutputToContain('Placeholder __KIT_UNKNOWN__ had no value.')
            ->assertSuccessful();
    });

    test('fails when the answers file does not exist', function (): void {
        artisan('starter-kit:install', ['--answers' => '/nonexistent/answers.json'])
            ->expectsOutputToContain('Answers file [/nonexistent/answers.json] not found.')
            ->assertFailed();
    });

    test('fails when the answers file is not a JSON object', function (): void {
        $file = sys_get_temp_dir().'/answers_'.uniqid().'.json';
        file_put_contents($file, 'not json');

        artisan('starter-kit:install', ['--answers' => $file])
            ->expectsOutputToContain('is not a valid JSON object.')
            ->assertFailed();
    });

    test('fails with the validation message when an answer is wrong', function (): void {
        $file = sys_get_temp_dir().'/answers_'.uniqid().'.json';
        file_put_contents($file, json_encode(['app_name' => 'A', 'app_type' => 'desktop']));

        artisan('starter-kit:install', ['--answers' => $file])
            ->expectsOutputToContain('The answer [app_type] must be one of')
            ->assertFailed();
    });

    test('fails when a module is missing', function (): void {
        $project = createProjectFixture();
        $answers_file = $project.'/answers.json';
        file_put_contents($answers_file, json_encode(['app_name' => 'A', 'app_type' => 'api', 'modules' => ['money']]));

        artisan('starter-kit:install', ['--answers' => $answers_file, '--base-path' => $project])
            ->expectsOutputToContain('Missing starter kit modules: money.')
            ->assertFailed();
    });
});

describe('with the questions', function (): void {
    test('asks the minimal questions for an API only app', function (): void {
        $project = createProjectFixture();

        artisan('starter-kit:install', ['--base-path' => $project])
            ->expectsQuestion('Application name', 'Quiet API')
            ->expectsQuestion('Slug (Docker images, database, network)', 'quiet-api')
            ->expectsQuestion('Vendor (Docker image, composer)', 'acme')
            ->expectsChoice('License', 'MIT', ['MIT', 'proprietary', 'Apache-2.0', 'GPL-3.0-or-later'])
            ->expectsQuestion('Local port', '8080')
            ->expectsQuestion("Users' real timezone", 'Europe/Rome')
            ->expectsQuestion('GitHub repository (owner/repo, optional)', '')
            ->expectsChoice('Application type', 'api', ['site_with_backoffice', 'backoffice', 'site', 'api', 'Public site + Filament back office', 'Filament back office only', 'Public site without back office', 'API only'])
            ->expectsConfirmation('Audit logins?', 'no')
            ->expectsConfirmation('Multilingual UI?', 'no')
            ->expectsChoice('Roles and permissions', 'none', ['none', 'permission', 'None', 'spatie/laravel-permission'])
            ->expectsChoice('Optional modules', [], [
                'socialite', 'money', 'media-library', 'settings', 'tags', 'saloon', 'standards', 'backup', 'csv', 'pdf', 'scout', 'slack', 'newsletter', 'sitemap',
                'Social login (Socialite)', 'Money (AsMoney cast, minor units)', 'Media library', 'Settings', 'Tags', 'Saloon (API integrations)',
                'ISO standards (countries, currencies, languages)', 'Backups', 'CSV import/export', 'PDF generation', 'Search (Scout + Meilisearch)',
                'Slack notifications', 'Newsletter', 'Sitemap',
            ])
            ->expectsConfirmation('Add the default deploy workflow?', 'no')
            ->expectsConfirmation('Add the AI tooling (Boost, rules, guidelines)?', 'no')
            ->expectsConfirmation('Is the app behind Cloudflare?', 'no')
            ->assertSuccessful();

        $saved = json_decode((string) file_get_contents($project.'/starter-kit.json'), true);

        expect($saved['app_name'])->toBe('Quiet API')
            ->and($saved['vendor'])->toBe('acme')
            ->and($saved['app_type'])->toBe('api')
            ->and($saved['ai_tooling'])->toBeFalse();
    });
    test('asks whether to upload the backups to Google Drive when the backup module is selected', function (): void {
        $project = createProjectFixture();
        mkdir($project.'/.starterkit/backup', 0755, true);
        writeModuleManifest($project, 'backup', "['variants' => ['google-drive' => []]]");

        artisan('starter-kit:install', ['--base-path' => $project])
            ->expectsQuestion('Application name', 'Safe API')
            ->expectsQuestion('Slug (Docker images, database, network)', 'safe-api')
            ->expectsQuestion('Vendor (Docker image, composer)', 'acme')
            ->expectsChoice('License', 'MIT', ['MIT', 'proprietary', 'Apache-2.0', 'GPL-3.0-or-later'])
            ->expectsQuestion('Local port', '8080')
            ->expectsQuestion("Users' real timezone", 'Europe/Rome')
            ->expectsQuestion('GitHub repository (owner/repo, optional)', '')
            ->expectsChoice('Application type', 'api', ['site_with_backoffice', 'backoffice', 'site', 'api', 'Public site + Filament back office', 'Filament back office only', 'Public site without back office', 'API only'])
            ->expectsConfirmation('Audit logins?', 'no')
            ->expectsConfirmation('Multilingual UI?', 'no')
            ->expectsChoice('Roles and permissions', 'none', ['none', 'permission', 'None', 'spatie/laravel-permission'])
            ->expectsChoice('Optional modules', ['backup'], [
                'socialite', 'money', 'media-library', 'settings', 'tags', 'saloon', 'standards', 'backup', 'csv', 'pdf', 'scout', 'slack', 'newsletter', 'sitemap',
                'Social login (Socialite)', 'Money (AsMoney cast, minor units)', 'Media library', 'Settings', 'Tags', 'Saloon (API integrations)',
                'ISO standards (countries, currencies, languages)', 'Backups', 'CSV import/export', 'PDF generation', 'Search (Scout + Meilisearch)',
                'Slack notifications', 'Newsletter', 'Sitemap',
            ])
            ->expectsConfirmation('Upload backups to Google Drive?', 'yes')
            ->expectsConfirmation('Add the default deploy workflow?', 'no')
            ->expectsConfirmation('Add the AI tooling (Boost, rules, guidelines)?', 'no')
            ->expectsConfirmation('Is the app behind Cloudflare?', 'no')
            ->assertSuccessful();

        $saved = json_decode((string) file_get_contents($project.'/starter-kit.json'), true);

        expect($saved['modules'])->toBe(['backup'])
            ->and($saved['backup_google_drive'])->toBeTrue();
    });

    test('asks the Filament, localization, permissions, social login and AI tooling questions', function (): void {
        $project = createProjectFixture();

        foreach (['filament', 'email-domain', 'force-password-change', 'filament-mfa', 'mfa-reset', 'login-audit', 'multilingual', 'localized-routes', 'translatable', 'permission', 'shield', 'socialite', 'money', 'ai-tooling', 'codegraph', 'cloudflare'] as $module) {
            mkdir($project.'/.starterkit/'.$module, 0755, true);
        }

        foreach (['translatable', 'permission'] as $module) {
            writeModuleManifest($project, $module, "['variants' => ['filament' => []]]");
        }

        writeModuleManifest($project, 'socialite', "['variants' => ['google' => [], 'github' => []]]");

        $optional_modules = [
            'socialite', 'money', 'media-library', 'settings', 'tags', 'saloon', 'standards', 'backup', 'csv', 'pdf', 'scout', 'slack', 'newsletter', 'sitemap',
            'Social login (Socialite)', 'Money (AsMoney cast, minor units)', 'Media library', 'Settings', 'Tags', 'Saloon (API integrations)',
            'ISO standards (countries, currencies, languages)', 'Backups', 'CSV import/export', 'PDF generation', 'Search (Scout + Meilisearch)',
            'Slack notifications', 'Newsletter', 'Sitemap',
        ];

        artisan('starter-kit:install', ['--base-path' => $project])
            ->expectsQuestion('Application name', 'Full App')
            ->expectsQuestion('Slug (Docker images, database, network)', 'full-app')
            ->expectsQuestion('Vendor (Docker image, composer)', 'acme')
            ->expectsChoice('License', 'proprietary', ['MIT', 'proprietary', 'Apache-2.0', 'GPL-3.0-or-later'])
            ->expectsQuestion('Local port', '9000')
            ->expectsQuestion("Users' real timezone", 'Europe/Rome')
            ->expectsQuestion('GitHub repository (owner/repo, optional)', 'acme/full-app')
            ->expectsChoice('Application type', 'site_with_backoffice', ['site_with_backoffice', 'backoffice', 'site', 'api', 'Public site + Filament back office', 'Filament back office only', 'Public site without back office', 'API only'])
            ->expectsQuestion('Main panel id', 'back-office')
            ->expectsConfirmation('Restrict the panel to some email domains?', 'yes')
            ->expectsQuestion('Allowed domains (comma separated)', 'a.com, b.com')
            ->expectsConfirmation('Force a password change for users created by an admin?', 'yes')
            ->expectsChoice('Two-factor authentication', 'required', ['none', 'optional', 'required', 'No', 'Optional', 'Required'])
            ->expectsConfirmation('Add a command to reset a user MFA?', 'yes')
            ->expectsConfirmation('Audit logins?', 'yes')
            ->expectsConfirmation('Multilingual UI?', 'yes')
            ->expectsChoice('Languages', ['en', 'it'], ['en', 'it', 'fr', 'de', 'es', 'English', 'Italian', 'French', 'German', 'Spanish'])
            ->expectsChoice('Default language', 'it', ['en', 'it', 'en', 'it'])
            ->expectsConfirmation('Localized URLs (mcamara)?', 'yes')
            ->expectsConfirmation('Translatable content?', 'yes')
            ->expectsChoice('Roles and permissions', 'shield', ['none', 'shield', 'permission', 'None', 'Filament Shield', 'spatie/laravel-permission only'])
            ->expectsChoice('Optional modules', ['socialite', 'money'], $optional_modules)
            ->expectsChoice('Social providers', ['google', 'github'], ['google', 'github', 'microsoft', 'apple', 'other', 'Google', 'GitHub', 'Microsoft', 'Apple', 'Other'])
            ->expectsConfirmation('Register new users on their first social login?', 'yes')
            ->expectsConfirmation('Add the default deploy workflow?', 'no')
            ->expectsConfirmation('Add the AI tooling (Boost, rules, guidelines)?', 'yes')
            ->expectsQuestion('GitHub token (kept in .env only)', 'ghp_secret')
            ->expectsQuestion('Context7 API key (kept in .env only)', 'c7_secret')
            ->expectsConfirmation('Add CodeGraph (local code index and MCP server)?', 'yes')
            ->expectsConfirmation('Is the app behind Cloudflare?', 'yes')
            ->assertSuccessful();

        $saved = json_decode((string) file_get_contents($project.'/starter-kit.json'), true);

        expect($saved)->toMatchArray([
            'panel_id' => 'back-office',
            'allowed_email_domains' => ['a.com', 'b.com'],
            'force_password_change' => true,
            'two_factor' => 'required',
            'mfa_reset' => true,
            'locales' => ['en', 'it'],
            'default_locale' => 'it',
            'permissions' => 'shield',
            'modules' => ['money'],
            'socialite_providers' => ['google', 'github'],
            'socialite_registration' => true,
            'ai_tooling' => true,
            'codegraph' => true,
            'cloudflare' => true,
        ])->not->toHaveKeys(['gh_token', 'context7_key']);
    });
});
