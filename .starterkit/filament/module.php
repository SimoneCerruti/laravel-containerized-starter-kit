<?php

declare(strict_types=1);

/*
 * Filament panel. The panel provider stub is named after the panel id chosen at install time.
 * `User::canAccessPanel()` requires every condition listed at `@kit:user-panel-access`: modules that
 * restrict access (email domains, roles) add theirs there.
 * Socialite's login buttons plug into `@kit:panel-hooks`.
 */
return [
    'require' => [
        'filament/filament' => '^5.0',
    ],
    'json' => [
        'composer' => [
            'scripts.post-autoload-dump' => ['@php artisan filament:upgrade'],
        ],
    ],
    'fragments' => [
        'tests/ArchTest.php' => [
            'arch-laravel-ignores' => "'App\\Providers\\Filament',",
        ],
        'bootstrap/providers.php' => [
            'providers-imports' => 'use App\Providers\Filament\__KIT_PANEL_CLASS__PanelProvider;',
            'providers' => '__KIT_PANEL_CLASS__PanelProvider::class,',
        ],
        'docker/scripts/rebuild-caches.sh' => [
            'rebuild-caches' => 'run "Optimizing filament"       artisan filament:optimize',
        ],
        'app/Providers/AppServiceProvider.php' => [
            'app-imports' => "use Filament\\Support\\Facades\\FilamentTimezone;\nuse Filament\\Tables\\Table;",
            'app-boot' => "FilamentTimezone::set(config()->string('app.actual_timezone'));\nTable::configureUsing(fn (Table \$table): Table => \$table->defaultDateDisplayFormat('d/m/Y')->defaultDateTimeDisplayFormat('d/m/Y H:i'));",
        ],
        'app/Models/User.php' => [
            'user-imports' => "use Filament\\Models\\Contracts\\FilamentUser;\nuse Filament\\Models\\Contracts\\HasName;\nuse Filament\\Panel;",
            'user-implements' => 'FilamentUser, HasName,',
            'user-methods' => 'fragments/user-methods.php',
        ],
    ],
    'when_disabled' => [
        'remove_files' => [
            '.ai/rules/filament.md',
            '.ai/rules/testing_filament.md',
            '.ai/rules/enums_filament.md',
            '.ai/rules/testing_models_filament.md',
        ],
    ],
];
