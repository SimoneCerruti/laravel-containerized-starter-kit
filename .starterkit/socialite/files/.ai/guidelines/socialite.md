# Socialite

- Never link a provider identity to a user by an email the provider has not verified; `ResolveUserFromSocialite` is the only place that decides it.
- A provider whose email claim is not reliably verified (multi-tenant Microsoft, custom OIDC) is refused until it is listed in `SOCIALITE_TRUSTED_PROVIDERS`.
- Do not store provider tokens unless the application calls the provider's API (`SOCIALITE_STORE_TOKENS`); stored tokens are encrypted and must stay out of activity logs.
- Register non-built-in providers in `App\Providers\SocialiteServiceProvider`, never in `AppServiceProvider`.
