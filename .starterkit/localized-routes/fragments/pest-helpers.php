/**
 * Registers the web routes again as if the first URL segment were the locale: the localized route
 * group reads it while the routes are registered, so one registration serves one locale.
 */
function loadRoutesForLocale(string $locale): void
{
    putenv("ROUTING_LOCALE={$locale}");

    Route::setRoutes(new RouteCollection);
    Route::middleware('web')->group(base_path('routes/web.php'));
    Route::getRoutes()->refreshNameLookups();
    Route::getRoutes()->refreshActionLookups();
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
