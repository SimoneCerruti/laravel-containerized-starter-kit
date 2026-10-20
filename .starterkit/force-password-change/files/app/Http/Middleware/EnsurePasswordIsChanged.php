<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Filament\Pages\EditProfile;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects users who have not chosen their own password (e.g. created by an admin) to the profile page.
 * Profile, logout, email verification and MFA set-up routes are exempt.
 */
final class EnsurePasswordIsChanged {
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response {
        /** @var User|null $user */
        $user = $request->user();

        $panel = Filament::getCurrentOrDefaultPanel();

        if ($user === null || $panel === null || $user->password_changed_at !== null || $this->isExemptRoute($request, $panel)) {
            return $next($request);
        }

        return redirect()->route(EditProfile::getRouteName($panel));
    }

    private function isExemptRoute(Request $request, Panel $panel): bool {
        return $request->routeIs(
            EditProfile::getRouteName($panel),
            $panel->generateRouteName('auth.logout'),
            $panel->generateRouteName('auth.email-verification.*'),
            $panel->getSetUpRequiredMultiFactorAuthenticationRouteName(),
        );
    }
}
