<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Http\Middleware\ApplyUserLocale;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

test('applies the locale of the signed-in user', function (): void {
    $locale = Locale::cases()[array_key_last(Locale::cases())];
    $request = Request::create('/');
    $request->setUserResolver(fn (): User => User::factory()->make(['locale' => $locale]));

    new ApplyUserLocale()->handle($request, fn (): Response => new Response);

    expect(app()->getLocale())->toBe($locale->value);
});

test('leaves the locale alone for guests', function (): void {
    app()->setLocale('en');

    new ApplyUserLocale()->handle(Request::create('/'), fn (): Response => new Response);

    expect(app()->getLocale())->toBe('en');
});
