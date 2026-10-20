<?php

declare(strict_types=1);

/*
 * Translatable model attributes (spatie/laravel-translatable). With the panel, the lara-zeus
 * plugin adds the locale switcher to resources.
 */
return [
    'require' => [
        'spatie/laravel-translatable' => '^6.15',
    ],
    'variants' => [
        'filament' => [
            'require' => ['lara-zeus/spatie-translatable' => '^2.0'],
            'fragments' => [
                'app/Providers/Filament/__KIT_PANEL_CLASS__PanelProvider.php' => [
                    'panel-imports' => 'use LaraZeus\SpatieTranslatable\SpatieTranslatablePlugin;',
                    'panel-plugins' => 'SpatieTranslatablePlugin::make()->defaultLocales([config(\'app.locale\')]),',
                ],
            ],
        ],
    ],
];
