<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function (): void {
    config()->set('socialite.providers', ['google']);
    config()->set('socialite.registration', true);
    config()->set('socialite.login_url', '/login');
    config()->set('socialite.home_url', '/');
});

function fakeGoogleUser(string $email = 'jane@example.com', bool $verified = true): SocialiteUser {
    $user = new SocialiteUser;
    $user->map(['id' => '1001', 'name' => 'Jane Doe', 'email' => $email]);
    $user->setRaw(['email_verified' => $verified]);

    return $user;
}

describe('callback', function (): void {
    it('signs a new user in and redirects home', function (): void {
        Socialite::fake('google', fakeGoogleUser());

        $this->get(route('socialite.callback', 'google'))->assertRedirect('/');

        $this->assertAuthenticated();
        expect(User::query()->where('email', 'jane@example.com')->exists())->toBeTrue();
    });

    it('signs an existing verified user in', function (): void {
        $user = User::factory()->create(['email' => 'jane@example.com']);
        Socialite::fake('google', fakeGoogleUser());

        $this->get(route('socialite.callback', 'google'));

        $this->assertAuthenticatedAs($user);
    });

    it('goes back to the login with the reason when the identity is refused', function (): void {
        Socialite::fake('google', fakeGoogleUser(verified: false));

        $this->get(route('socialite.callback', 'google'))
            ->assertRedirect('/login')
            ->assertSessionHas('socialite.error');

        $this->assertGuest();
    });

    it('goes back to the login when the OAuth state is invalid', function (): void {
        Socialite::shouldReceive('driver->user')->andThrow(new InvalidStateException);

        $this->get(route('socialite.callback', 'google'))
            ->assertRedirect('/login')
            ->assertSessionHas('socialite.error');

        $this->assertGuest();
    });
});
