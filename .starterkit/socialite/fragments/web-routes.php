Route::middleware('guest')->prefix('auth')->name('socialite.')->group(function (): void {
    Route::get('{provider}/redirect', \App\Http\Controllers\Auth\SocialiteRedirectController::class)
        ->whereIn('provider', config()->array('socialite.providers'))
        ->name('redirect');
    Route::get('{provider}/callback', \App\Http\Controllers\Auth\SocialiteCallbackController::class)
        ->whereIn('provider', config()->array('socialite.providers'))
        ->name('callback');
});
