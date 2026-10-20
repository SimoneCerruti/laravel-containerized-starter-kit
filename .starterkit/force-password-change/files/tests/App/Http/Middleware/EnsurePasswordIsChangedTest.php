<?php

declare(strict_types=1);

use App\Filament\Pages\EditProfile;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

function passwordMiddlewareRequest(?User $user): Request {
    $request = Request::create('/');
    $request->setUserResolver(fn (): ?User => $user);

    return $request;
}

test('lets guests through', function (): void {
    $response = new EnsurePasswordIsChanged()->handle(passwordMiddlewareRequest(null), fn (): Response => new Response('next'));

    expect($response->getContent())->toBe('next');
});

test('lets through users who chose their own password', function (): void {
    $user = User::factory()->make(['password_changed_at' => now()]);

    $response = new EnsurePasswordIsChanged()->handle(passwordMiddlewareRequest($user), fn (): Response => new Response('next'));

    expect($response->getContent())->toBe('next');
});

test('sends users who still have the initial password to the profile page', function (): void {
    $panel = Filament::getDefaultPanel();
    Filament::setCurrentPanel($panel);
    $user = User::factory()->withInitialPassword()->make();

    $response = new EnsurePasswordIsChanged()->handle(passwordMiddlewareRequest($user), fn (): Response => new Response('next'));

    expect($response->isRedirect(route(EditProfile::getRouteName($panel))))->toBeTrue();
});
