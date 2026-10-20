<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class SocialiteRedirectController {
    public function __invoke(string $provider): RedirectResponse {
        return Socialite::driver($provider)->redirect();
    }
}
