/**
 * Boots the application as if the first URL segment were the locale: the localized route group
 * reads it while the routes are registered, so one boot serves one locale.
 */
function bootApplicationForLocale(string $locale): void
{
    putenv("ROUTING_LOCALE={$locale}");

    test()->refreshApplication();
}

/**
 * Names of the GET routes that live in the localized group.
 *
 * @return list<string>
 */
function getLocalizedPublicRouteNames(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LocalizedRoute $route): bool => \in_array('GET', $route->methods(), true)
            && $route->getName() !== null
            && \in_array('localize', $route->gatherMiddleware(), true))
        ->map(fn (LocalizedRoute $route): string => (string) $route->getName())
        ->values()
        ->all();
}
