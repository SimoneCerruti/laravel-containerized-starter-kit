<?php

declare(strict_types=1);

use App\Console\Commands\StarterKit\PlaceholderReplacer;

describe('replace', function (): void {
    test('replaces every occurrence of the provided placeholders', function (): void {
        $replacer = new PlaceholderReplacer(['KIT_APP_SLUG' => 'my-app', 'KIT_APP_PORT' => '8080']);

        expect($replacer->replace('image: __KIT_APP_SLUG__ / __KIT_APP_SLUG___test:__KIT_APP_PORT__'))
            ->toBe('image: my-app / my-app_test:8080');
    });

    test('works on file paths too', function (): void {
        $replacer = new PlaceholderReplacer(['KIT_PANEL_CLASS' => 'Admin']);

        expect($replacer->replace('app/Providers/Filament/__KIT_PANEL_CLASS__PanelProvider.php'))
            ->toBe('app/Providers/Filament/AdminPanelProvider.php');
    });

    test('leaves placeholders without a value untouched', function (): void {
        expect((new PlaceholderReplacer(['KIT_APP_SLUG' => 'x']))->replace('__KIT_VENDOR__'))->toBe('__KIT_VENDOR__');
    });
});

describe('findUnresolved', function (): void {
    test('lists the placeholders no value was provided for', function (): void {
        $replacer = new PlaceholderReplacer(['KIT_APP_SLUG' => 'x']);

        expect($replacer->findUnresolved('__KIT_APP_SLUG__ __KIT_VENDOR__ __KIT_VENDOR__ __KIT_GH_TOKEN__'))
            ->toBe(['__KIT_VENDOR__', '__KIT_GH_TOKEN__']);
    });

    test('is empty once everything is resolved', function (): void {
        expect((new PlaceholderReplacer(['KIT_APP_SLUG' => 'x']))->findUnresolved('__KIT_APP_SLUG__'))->toBe([]);
    });
});
