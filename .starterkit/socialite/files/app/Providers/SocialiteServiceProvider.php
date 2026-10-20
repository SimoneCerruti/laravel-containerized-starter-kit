<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

final class SocialiteServiceProvider extends ServiceProvider {
    public function boot(): void {
        Event::listen(function (SocialiteWasCalled $event): void {
            // @kit:socialite-listeners
        });
    }
}
