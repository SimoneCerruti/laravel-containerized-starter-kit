<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\StarterKit\KitAnswers;
use App\Console\Commands\StarterKit\KitInstaller;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Safe\Exceptions\JsonException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Safe\file_get_contents;
use function Safe\json_decode;

#[Signature('starter-kit:install {--answers= : Path of a JSON file with the answers, to run without questions} {--base-path= : Project to install into (defaults to this one)}')]
#[Description('Ask the setup questions, install the selected modules and remove the starter kit')]
final class InstallStarterKitCommand extends Command {
    private const array OPTIONAL_MODULES = [
        'socialite' => 'Social login (Socialite)',
        'money' => 'Money (AsMoney cast, minor units)',
        'media-library' => 'Media library',
        'settings' => 'Settings',
        'tags' => 'Tags',
        'saloon' => 'Saloon (API integrations)',
        'standards' => 'ISO standards (countries, currencies, languages)',
        'backup' => 'Backups',
        'csv' => 'CSV import/export',
        'pdf' => 'PDF generation',
        'scout' => 'Search (Scout + Meilisearch)',
        'slack' => 'Slack notifications',
        'newsletter' => 'Newsletter',
        'sitemap' => 'Sitemap',
        'debugbar' => 'Debugbar (development only)',
        'nightwatch' => 'Nightwatch (monitoring)',
    ];

    public function handle(): int {
        try {
            $answers = $this->option('answers') === null ? $this->askQuestions() : $this->readAnswers((string) $this->option('answers'));
            $base_path = (string) ($this->option('base-path') ?: base_path());

            $unresolved = new KitInstaller($base_path, fn (string $message) => $this->components->info($message))->install($answers);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($unresolved as $placeholder) {
            $this->components->warn("Placeholder {$placeholder} had no value.");
        }

        $this->components->info('Starter kit installed and removed.');

        return self::SUCCESS;
    }

    private function readAnswers(string $file): KitAnswers {
        if (! is_file($file)) {
            throw new InvalidArgumentException("Answers file [{$file}] not found.");
        }

        try {
            $decoded = json_decode(file_get_contents($file), true);
        } catch (JsonException) {
            $decoded = null;
        }

        if (! \is_array($decoded)) {
            throw new InvalidArgumentException("Answers file [{$file}] is not a valid JSON object.");
        }

        return KitAnswers::fromArray($decoded);
    }

    private function askQuestions(): KitAnswers {
        $app_name = text('Application name', required: true);
        $app_slug = text('Slug (Docker images, database, network)', default: Str::slug($app_name), required: true);

        $answers = [
            'app_name' => $app_name,
            'app_slug' => $app_slug,
            'vendor' => text('Vendor (Docker image, composer)', default: $app_slug, required: true),
            'license' => select('License', KitAnswers::LICENSES, default: 'MIT'),
            'port' => (int) text('Local port', default: '8080', required: true, validate: fn (string $value): ?string => ctype_digit($value) ? null : 'The port must be a number.'),
            'timezone' => text("Users' real timezone", default: 'Europe/Rome', required: true),
            'gh_repository' => text('GitHub repository (owner/repo, optional)'),
            'author_name' => $author_name = text('Author name (optional)'),
            'author_email' => $author_name === '' ? '' : text('Author email (optional)'),
            'app_type' => select('Application type', [
                'site_with_backoffice' => 'Public site + Filament back office',
                'backoffice' => 'Filament back office only',
                'site' => 'Public site without back office',
                'api' => 'API only',
            ], default: 'site_with_backoffice'),
        ];

        $has_filament = \in_array($answers['app_type'], ['backoffice', 'site_with_backoffice'], true);
        $has_public_site = \in_array($answers['app_type'], ['site_with_backoffice', 'site'], true);

        if ($has_filament) {
            $answers['panel_id'] = text('Main panel id', default: 'admin', required: true);
            $answers['allowed_email_domains'] = $this->askEmailDomains();
            $answers['force_password_change'] = confirm('Force a password change for users created by an admin?', default: false);
            $answers['two_factor'] = select('Two-factor authentication', ['none' => 'No', 'optional' => 'Optional', 'required' => 'Required'], default: 'none');
            $answers['mfa_reset'] = $answers['two_factor'] !== 'none' && confirm('Add a command to reset a user MFA?', default: true);
        }

        $answers['login_audit'] = confirm('Audit logins?', default: true);

        $answers = [...$answers, ...$this->askLocalization($has_public_site)];

        $answers['permissions'] = select('Roles and permissions', $has_filament
            ? ['none' => 'None', 'shield' => 'Filament Shield', 'permission' => 'spatie/laravel-permission only']
            : ['none' => 'None', 'permission' => 'spatie/laravel-permission'], default: 'none');

        $answers['modules'] = multiselect('Optional modules', self::OPTIONAL_MODULES);
        $answers = [...$answers, ...$this->askSocialite($answers['modules'], $has_public_site)];
        $answers['modules'] = array_values(array_diff($answers['modules'], ['socialite']));
        $answers['backup_google_drive'] = \in_array('backup', $answers['modules'], true) && confirm('Upload backups to Google Drive?', default: false);

        $answers['deploy'] = confirm('Add the default deploy workflow?', default: false);
        $answers = [...$answers, ...$this->askAiTooling()];
        $answers['cloudflare'] = confirm('Is the app behind Cloudflare?', default: false);
        $answers['setup_guide'] = confirm('Write SETUP.md with the steps to do outside the app (deploy, monitoring, ...)?', default: true);

        return KitAnswers::fromArray($answers);
    }

    /**
     * @return list<string>
     */
    private function askEmailDomains(): array {
        if (! confirm('Restrict the panel to some email domains?', default: false)) {
            return [];
        }

        $domains = text('Allowed domains (comma separated)', required: true);

        return array_values(array_filter(array_map(trim(...), explode(',', $domains))));
    }

    /**
     * @return array<string, mixed>
     */
    private function askLocalization(bool $has_public_site): array {
        if (! confirm('Multilingual UI?', default: false)) {
            return [];
        }

        $locales = multiselect('Languages', ['en' => 'English', 'it' => 'Italian', 'fr' => 'French', 'de' => 'German', 'es' => 'Spanish'], default: ['en'], required: true);
        $answers = [
            'multilingual' => true,
            'locales' => $locales,
            'default_locale' => select('Default language', array_combine($locales, $locales)),
        ];

        if ($has_public_site) {
            $answers['localized_routes'] = confirm('Localized URLs (mcamara)?', default: true);
            $answers['translatable'] = confirm('Translatable content?', default: true);
        }

        return $answers;
    }

    /**
     * @param  list<string>  $modules
     * @return array<string, mixed>
     */
    private function askSocialite(array $modules, bool $has_public_site): array {
        if (! \in_array('socialite', $modules, true)) {
            return [];
        }

        return [
            'socialite_providers' => multiselect('Social providers', ['google' => 'Google', 'github' => 'GitHub', 'microsoft' => 'Microsoft', 'apple' => 'Apple', 'other' => 'Other'], required: true),
            'socialite_registration' => $has_public_site && confirm('Register new users on their first social login?', default: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function askAiTooling(): array {
        if (! confirm('Add the AI tooling (Boost, rules, guidelines)?', default: true)) {
            return ['ai_tooling' => false];
        }

        return [
            'ai_tooling' => true,
            'gh_token' => password('GitHub token (kept in .env only)'),
            'context7_key' => password('Context7 API key (kept in .env only)'),
            'codegraph' => confirm('Add CodeGraph (local code index and MCP server)?', default: false),
        ];
    }
}
