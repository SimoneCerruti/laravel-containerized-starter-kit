<?php

declare(strict_types=1);

use App\Models\SocialAccount;
use Illuminate\Database\UniqueConstraintViolationException;

describe('attributes', function (): void {
    describe('token', function (): void {
        it('is encrypted at rest and hidden from serialization', function (): void {
            $account = SocialAccount::factory()->withTokens()->create(['token' => 'secret']);

            expect($account->getRawOriginal('token'))->not->toBe('secret')
                ->and($account->fresh()->token)->toBe('secret')
                ->and($account->toArray())->not->toHaveKeys(['token', 'refresh_token']);
        });
    });

    describe('expires_at', function (): void {
        it('is cast to an immutable datetime', function (): void {
            expect(SocialAccount::factory()->withTokens()->create()->expires_at)->toBeInstanceOf(DateTimeImmutable::class);
        });
    });

    describe('provider_user_id', function (): void {
        it('is unique per provider', function (): void {
            SocialAccount::factory()->forProvider('google')->create(['provider_user_id' => '1']);

            SocialAccount::factory()->forProvider('google')->create(['provider_user_id' => '1']);
        })->throws(UniqueConstraintViolationException::class);

        it('may repeat across providers', function (): void {
            SocialAccount::factory()->forProvider('google')->create(['provider_user_id' => '1']);

            expect(SocialAccount::factory()->forProvider('github')->create(['provider_user_id' => '1'])->exists)->toBeTrue();
        });
    });
});

describe('mass-assignment', function (): void {
    it('allows only the declared attributes', function (): void {
        $account = new SocialAccount;

        expect($account->getFillable())->toBe(['user_id', 'provider', 'provider_user_id', 'token', 'refresh_token', 'expires_at']);
    });
});

describe('activity logging', function (): void {
    it('never logs the tokens', function (): void {
        $account = SocialAccount::factory()->withTokens()->create();

        $properties = $account->activitiesAsSubject()->get()->map(fn ($activity): array => $activity->properties->toArray())->toJson();

        expect($properties)->not->toContain('token');
    });
});

describe('relations', function (): void {
    it('belongs to a user that lists it back', function (): void {
        $account = SocialAccount::factory()->create();

        expect($account->user->socialAccounts->first()?->is($account))->toBeTrue();
    });
});

describe('factory', function (): void {
    it('builds a token-less account by default', function (): void {
        expect(SocialAccount::factory()->create()->token)->toBeNull();
    });
});
