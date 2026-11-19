<?php

declare(strict_types=1);

use App\Enums\Locale;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;
use function Safe\putenv;

afterEach(function (): void {
    putenv('ROUTING_LOCALE');
});

it('serves every named public route under every supported locale', function (Locale $locale): void {
    bootApplicationForLocale($locale->value);

    $names = getLocalizedPublicRouteNames();

    expect($names)->not->toBeEmpty();

    foreach ($names as $name) {
        expect(Route::has($name))->toBeTrue()
            ->and(route($name))->toContain("/{$locale->value}");

        get(route($name))->assertOk();
    }
})->with(Locale::cases());

it('does not serve a locale prefix that is not supported', function (): void {
    expect(array_column(Locale::cases(), 'value'))->not->toContain('xx');

    get('/xx')->assertNotFound();
});
