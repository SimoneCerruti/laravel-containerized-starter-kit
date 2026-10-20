<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;

beforeEach(function (): void {
    config()->set('socialite.providers', ['google']);
    config()->set('socialite.registration', true);
    config()->set('socialite.login_url', '/login');
    config()->set('socialite.home_url', '/');
});

describe('redirect', function (): void {
    it('sends a guest to the provider', function (): void {
        Socialite::fake('google');

        $this->get(route('socialite.redirect', 'google'))->assertRedirect();
    });

    it('rejects a provider that is not enabled', function (): void {
        $this->get('/auth/github/redirect')->assertNotFound();
    });

    it('bounces a signed-in user', function (): void {
        $this->actingAs(User::factory()->create())
            ->get(route('socialite.redirect', 'google'))
            ->assertRedirect();
    });
});
