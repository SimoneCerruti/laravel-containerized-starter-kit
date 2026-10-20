<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\ResolveUserFromSocialite;
use App\Exceptions\SocialLoginRejected;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

final class SocialiteCallbackController {
    public function __invoke(Request $request, string $provider): RedirectResponse {
        try {
            $user = ResolveUserFromSocialite::run($provider, Socialite::driver($provider)->user());
        } catch (SocialLoginRejected $exception) {
            return $this->failed($exception->getMessage());
        } catch (InvalidStateException|ClientException) {
            return $this->failed(Lang::string('Sign-in with :provider failed. Please try again.', ['provider' => $provider]));
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(config()->string('socialite.home_url'));
    }

    private function failed(string $message): RedirectResponse {
        return redirect()->to(config()->string('socialite.login_url'))->with('socialite.error', $message);
    }
}
