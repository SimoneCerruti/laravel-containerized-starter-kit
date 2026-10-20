<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\SocialLoginRejected;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Two\User as TwoUser;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Maps a provider identity to a local user.
 *
 * A known identity signs in. Otherwise the email must be verified by the provider; it then links to
 * an existing verified user, or registers a new one when `socialite.registration` allows it.
 * Linking to a local account whose own email is unverified is refused, so nobody can pre-register
 * a victim's address and keep access after the victim signs in with a provider.
 */
final class ResolveUserFromSocialite {
    use AsAction;

    /**
     * @throws SocialLoginRejected
     */
    public function handle(string $provider, SocialiteUser $social_user): User {
        return DB::transaction(function () use ($provider, $social_user): User {
            $account = SocialAccount::query()
                ->where('provider', $provider)
                ->where('provider_user_id', (string) $social_user->getId())
                ->first();

            if ($account instanceof SocialAccount) {
                $this->storeTokens($account, $social_user);

                return $account->user;
            }

            $email = $this->verifiedEmail($provider, $social_user);

            $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

            if ($user instanceof User) {
                throw_if($user->email_verified_at === null, SocialLoginRejected::unverifiedLocalAccount());
            } else {
                throw_unless(config()->boolean('socialite.registration'), SocialLoginRejected::registrationDisabled());

                $user = $this->register($email, $social_user);
            }

            $this->storeTokens(
                new SocialAccount([
                    'user_id' => $user->id,
                    'provider' => $provider,
                    'provider_user_id' => (string) $social_user->getId(),
                ]),
                $social_user,
            );

            return $user;
        });
    }

    private function verifiedEmail(string $provider, SocialiteUser $social_user): string {
        $email = $social_user->getEmail();

        throw_if($email === null || $email === '', SocialLoginRejected::missingEmail());

        throw_unless($this->isEmailVerified($provider, $social_user), SocialLoginRejected::unverifiedEmail());

        return Str::lower($email);
    }

    private function isEmailVerified(string $provider, SocialiteUser $social_user): bool {
        if (\in_array($provider, config()->array('socialite.trusted_providers'), true)) {
            return true;
        }

        $raw = $social_user instanceof AbstractUser ? $social_user->user : [];

        return match ($provider) {
            'google', 'apple' => filter_var($raw['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            // Socialite only returns the primary, verified GitHub address.
            'github' => true,
            default => false,
        };
    }

    private function register(string $email, SocialiteUser $social_user): User {
        $name = mb_trim((string) $social_user->getName());
        $first_name = $name === '' ? Str::before($email, '@') : Str::before($name, ' ');
        $last_name = Str::contains($name, ' ') ? mb_trim(Str::after($name, ' ')) : '';

        $user = new User;
        $user->forceFill([
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Str::random(64),
        ])->save();

        return $user;
    }

    private function storeTokens(SocialAccount $account, SocialiteUser $social_user): void {
        if (config()->boolean('socialite.store_tokens') && $social_user instanceof TwoUser) {
            $account->fill([
                'token' => $social_user->token,
                'refresh_token' => $social_user->refreshToken,
                'expires_at' => $social_user->expiresIn === null ? null : now()->addSeconds($social_user->expiresIn),
            ]);
        }

        $account->save();
    }
}
