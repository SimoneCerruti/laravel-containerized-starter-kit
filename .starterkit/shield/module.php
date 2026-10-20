<?php

declare(strict_types=1);

/*
 * Filament Shield on top of the `permission` module (the installer always activates both).
 * Run `php artisan shield:generate --all` once the resources exist to create their permissions.
 */
return [
    'require' => [
        'bezhansalleh/filament-shield' => '^4.3',
    ],
    'fragments' => [
        'app/Providers/Filament/__KIT_PANEL_CLASS__PanelProvider.php' => [
            'panel-imports' => 'use BezhanSalleh\FilamentShield\FilamentShieldPlugin;',
            'panel-plugins' => 'FilamentShieldPlugin::make(),',
        ],
    ],
];
