<?php

declare(strict_types=1);

use App\Actions\ResolveUserFromSocialite;
use App\Exceptions\SocialLoginRejected;
use App\Models\SocialAccount;
use App\Models\User;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * @param  array<string, mixed>  $raw
 */
function socialiteUser(string $id = '1001', ?string $email = 'jane@example.com', string $name = 'Jane Doe', array $raw = ['email_verified' => true]): SocialiteUser {
    $user = new SocialiteUser;
    $user->map(['id' => $id, 'name' => $name, 'email' => $email]);
    $user->token = 'access-token';
    $user->refreshToken = 'refresh-token';
    $user->expiresIn = 3600;
    $user->setRaw($raw);

    return $user;
}

beforeEach(function (): void {
    config()->set('socialite.registration', true);
    config()->set('socialite.store_tokens', false);
    config()->set('socialite.trusted_providers', []);
});

describe('known identity', function (): void {
    it('returns the linked user without checking the email', function (): void {
        $account = SocialAccount::factory()->forProvider('google')->create(['provider_user_id' => '1001']);

        $user = ResolveUserFromSocialite::run('google', socialiteUser(email: null, raw: []));

        expect($user->is($account->user))->toBeTrue()
            ->and(SocialAccount::query()->count())->toBe(1);
    });

    it('does not mix up the same provider id on another provider', function (): void {
        SocialAccount::factory()->forProvider('github')->create(['provider_user_id' => '1001']);

        $user = ResolveUserFromSocialite::run('google', socialiteUser());

        expect(User::query()->count())->toBe(2)
            ->and($user->email)->toBe('jane@example.com');
    });
});

describe('existing user', function (): void {
    it('links a verified local account when the provider verified the email', function (): void {
        $existing = User::factory()->create(['email' => 'jane@example.com']);

        $user = ResolveUserFromSocialite::run('google', socialiteUser());

        expect($user->is($existing))->toBeTrue()
            ->and($existing->socialAccounts()->where('provider', 'google')->where('provider_user_id', '1001')->exists())->toBeTrue();
    });

    it('matches the email case-insensitively', function (): void {
        $existing = User::factory()->create(['email' => 'jane@example.com']);

        $user = ResolveUserFromSocialite::run('google', socialiteUser(email: 'Jane@Example.com'));

        expect($user->is($existing))->toBeTrue();
    });

    it('refuses a local account whose own email is unverified', function (): void {
        User::factory()->unverified()->create(['email' => 'jane@example.com']);

        ResolveUserFromSocialite::run('google', socialiteUser());
    })->throws(SocialLoginRejected::class);
});

describe('registration', function (): void {
    it('creates a verified user with the provider name split in two', function (): void {
        $user = ResolveUserFromSocialite::run('google', socialiteUser(name: 'Jane Van Doe'));

        expect($user->first_name)->toBe('Jane')
            ->and($user->last_name)->toBe('Van Doe')
            ->and($user->email)->toBe('jane@example.com')
            ->and($user->email_verified_at)->not->toBeNull()
            ->and($user->socialAccounts)->toHaveCount(1);
    });

    it('falls back to the email local part when the provider shares no name', function (): void {
        $user = ResolveUserFromSocialite::run('google', socialiteUser(name: ''));

        expect($user->first_name)->toBe('jane')
            ->and($user->last_name)->toBe('');
    });

    it('creates nothing when registration is disabled', function (): void {
        config()->set('socialite.registration', false);

        expect(fn () => ResolveUserFromSocialite::run('google', socialiteUser()))->toThrow(SocialLoginRejected::class)
            ->and(User::query()->count())->toBe(0)
            ->and(SocialAccount::query()->count())->toBe(0);
    });
});

describe('email verification', function (): void {
    it('refuses an identity without an email', function (): void {
        ResolveUserFromSocialite::run('google', socialiteUser(email: null));
    })->throws(SocialLoginRejected::class);

    it('refuses an email the provider did not verify', function (): void {
        ResolveUserFromSocialite::run('google', socialiteUser(raw: ['email_verified' => false]));
    })->throws(SocialLoginRejected::class);

    it('accepts the Apple string flag', function (): void {
        $user = ResolveUserFromSocialite::run('apple', socialiteUser(raw: ['email_verified' => 'true']));

        expect($user->exists)->toBeTrue();
    });

    it('trusts GitHub, which only returns verified addresses', function (): void {
        $user = ResolveUserFromSocialite::run('github', socialiteUser(raw: []));

        expect($user->exists)->toBeTrue();
    });

    it('refuses an unknown provider unless it is trusted', function (): void {
        expect(fn () => ResolveUserFromSocialite::run('microsoft', socialiteUser(raw: [])))->toThrow(SocialLoginRejected::class);

        config()->set('socialite.trusted_providers', ['microsoft']);

        expect(ResolveUserFromSocialite::run('microsoft', socialiteUser(raw: []))->exists)->toBeTrue();
    });
});

describe('tokens', function (): void {
    it('does not store tokens by default', function (): void {
        ResolveUserFromSocialite::run('google', socialiteUser());

        $account = SocialAccount::query()->firstOrFail();

        expect($account->token)->toBeNull()
            ->and($account->refresh_token)->toBeNull()
            ->and($account->expires_at)->toBeNull();
    });

    it('stores them encrypted when enabled', function (): void {
        config()->set('socialite.store_tokens', true);
        $this->freezeSecond();

        ResolveUserFromSocialite::run('google', socialiteUser());

        $account = SocialAccount::query()->firstOrFail();

        expect($account->token)->toBe('access-token')
            ->and($account->refresh_token)->toBe('refresh-token')
            ->and($account->expires_at?->equalTo(now()->addHour()))->toBeTrue()
            ->and($account->getRawOriginal('token'))->not->toBe('access-token');
    });

    it('refreshes the stored tokens on the next sign-in', function (): void {
        config()->set('socialite.store_tokens', true);
        SocialAccount::factory()->forProvider('google')->withTokens()->create(['provider_user_id' => '1001']);

        ResolveUserFromSocialite::run('google', socialiteUser());

        expect(SocialAccount::query()->firstOrFail()->token)->toBe('access-token');
    });
});
