<?php

declare(strict_types=1);

use App\Console\Commands\StarterKit\KitAnswers;

describe('fromArray', function (): void {
    test('derives the slug from the name and applies the defaults', function (): void {
        $answers = KitAnswers::fromArray(['app_name' => 'My Great App']);

        expect($answers->app_slug)->toBe('my-great-app')
            ->and($answers->app_type)->toBe('site_with_backoffice')
            ->and($answers->license)->toBe('MIT');
    });

    test('requires the application name', function (): void {
        expect(fn () => KitAnswers::fromArray([]))
            ->toThrow(InvalidArgumentException::class, 'The answer [app_name] is required.');
    });

    test('rejects answers it does not know', function (): void {
        expect(fn () => KitAnswers::fromArray(['app_name' => 'A', 'typo' => true]))
            ->toThrow(InvalidArgumentException::class, 'Unknown answers: typo.');
    });

    test('rejects a value outside the allowed ones', function (): void {
        expect(fn () => KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'desktop']))
            ->toThrow(InvalidArgumentException::class, 'The answer [app_type] must be one of');
    });
});

describe('license', function (): void {
    test('rejects a license without a text to write', function (): void {
        expect(fn () => KitAnswers::fromArray(['app_name' => 'A', 'license' => 'WTFPL']))
            ->toThrow(InvalidArgumentException::class, 'The answer [license] must be one of');
    });

    test('has a text for every license it accepts', function (): void {
        foreach (KitAnswers::LICENSES as $license) {
            expect(base_path(".starterkit/core/licenses/{$license}.txt"))->toBeFile();
        }
    });
});

describe('activeModules', function (): void {
    test('always includes core, and filament for the types with a back office', function (): void {
        expect(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api'])->activeModules())->toBe(['core'])
            ->and(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'site'])->activeModules())->toBe(['core'])
            ->and(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'backoffice'])->activeModules())->toBe(['core', 'filament'])
            ->and(KitAnswers::fromArray(['app_name' => 'A'])->activeModules())->toBe(['core', 'filament']);
    });

    test('adds the Filament modules the answers ask for, but only with Filament', function (): void {
        $filament = KitAnswers::fromArray(['app_name' => 'A', 'allowed_email_domains' => ['a.com'], 'force_password_change' => true, 'two_factor' => 'required', 'mfa_reset' => true]);
        $without_filament = KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api', 'allowed_email_domains' => ['a.com'], 'force_password_change' => true, 'two_factor' => 'required', 'mfa_reset' => true]);

        expect($filament->activeModules())->toBe(['core', 'filament', 'email-domain', 'force-password-change', 'filament-mfa', 'mfa-reset'])
            ->and($without_filament->activeModules())->toBe(['core']);
    });

    test('adds the MFA reset only when two-factor is enabled', function (): void {
        expect(KitAnswers::fromArray(['app_name' => 'A', 'two_factor' => 'none', 'mfa_reset' => true])->activeModules())->not->toContain('mfa-reset');
    });

    test('adds the multilingual modules, localized routes and translatable content only for a public site', function (): void {
        $site = KitAnswers::fromArray(['app_name' => 'A', 'multilingual' => true, 'localized_routes' => true, 'translatable' => true]);
        $backoffice = KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'backoffice', 'multilingual' => true, 'localized_routes' => true, 'translatable' => true]);

        expect($site->activeModules())->toContain('multilingual', 'localized-routes', 'translatable')
            ->and($backoffice->activeModules())->toContain('multilingual')
            ->not->toContain('localized-routes', 'translatable');
    });

    test('adds permission for any roles answer, plus shield with Filament', function (): void {
        expect(KitAnswers::fromArray(['app_name' => 'A', 'permissions' => 'shield'])->activeModules())->toContain('permission', 'shield')
            ->and(KitAnswers::fromArray(['app_name' => 'A', 'permissions' => 'permission'])->activeModules())->toContain('permission')
            ->and(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api', 'permissions' => 'shield'])->activeModules())->toContain('permission')->not->toContain('shield');
    });

    test('adds codegraph only together with the AI tooling', function (): void {
        expect(KitAnswers::fromArray(['app_name' => 'A', 'ai_tooling' => true, 'codegraph' => true])->activeModules())->toContain('ai-tooling', 'codegraph')
            ->and(KitAnswers::fromArray(['app_name' => 'A', 'codegraph' => true])->activeModules())->not->toContain('codegraph');
    });

    test('adds the infrastructure and optional modules without duplicates', function (): void {
        $answers = KitAnswers::fromArray(['app_name' => 'A', 'login_audit' => true, 'deploy' => true, 'ai_tooling' => true, 'cloudflare' => true, 'modules' => ['money', 'money', 'media-library'], 'socialite_providers' => ['google']]);

        expect($answers->activeModules())->toBe(['core', 'filament', 'login-audit', 'socialite', 'deploy', 'ai-tooling', 'cloudflare', 'money', 'media-library']);
    });
});

describe('variants', function (): void {
    test('maps the socialite providers to the module variants', function (): void {
        expect(KitAnswers::fromArray(['app_name' => 'A', 'socialite_providers' => ['google', 'apple']])->variants())->toBe(['socialite' => ['google', 'apple']])
            ->and(KitAnswers::fromArray(['app_name' => 'A'])->variants())->toBe([]);
    });

    test('adds the google-drive variant of the backup module only when the module is selected and Drive is wanted', function (): void {
        expect(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api', 'modules' => ['backup'], 'backup_google_drive' => true])->variants())->toBe(['backup' => ['google-drive']])
            ->and(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api', 'modules' => ['backup']])->variants())->toBe([])
            ->and(KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'api', 'backup_google_drive' => true])->variants())->toBe([]);
    });
});

describe('placeholders', function (): void {
    test('provides a value for every placeholder', function (): void {
        $placeholders = KitAnswers::fromArray(['app_name' => 'My App', 'port' => 9000, 'panel_id' => 'back-office', 'default_locale' => 'it', 'socialite_providers' => ['google', 'github'], 'socialite_registration' => true])->placeholders();

        expect($placeholders)->toMatchArray([
            'KIT_APP_NAME' => 'My App',
            'KIT_APP_SLUG' => 'my-app',
            'KIT_APP_PORT' => '9000',
            'KIT_PANEL_ID' => 'back-office',
            'KIT_PANEL_CLASS' => 'BackOffice',
            'KIT_DEFAULT_LOCALE_CASE' => 'IT',
            'KIT_SOCIALITE_PROVIDERS' => 'google,github',
            'KIT_SOCIALITE_REGISTRATION' => 'true',
            'KIT_SOCIALITE_HOME_URL' => '/back-office',
            'KIT_SOCIALITE_LOGIN_URL' => '/back-office/login',
        ]);
    });

    test('points the social login urls to the site when there is no Filament', function (): void {
        $placeholders = KitAnswers::fromArray(['app_name' => 'A', 'app_type' => 'site'])->placeholders();

        expect($placeholders['KIT_SOCIALITE_HOME_URL'])->toBe('/')
            ->and($placeholders['KIT_SOCIALITE_LOGIN_URL'])->toBe('/login')
            ->and($placeholders['KIT_SOCIALITE_REGISTRATION'])->toBe('false');
    });
});

describe('toArray', function (): void {
    test('round-trips through fromArray', function (): void {
        $answers = KitAnswers::fromArray(['app_name' => 'A', 'modules' => ['money']]);

        expect(KitAnswers::fromArray($answers->toArray()))->toEqual($answers);
    });
});
