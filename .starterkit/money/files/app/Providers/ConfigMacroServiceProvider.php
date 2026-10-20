<?php

declare(strict_types=1);

namespace App\Providers;

use App\Macros\Config\ResolveEnumMacro;
use Illuminate\Support\ServiceProvider;

final class ConfigMacroServiceProvider extends ServiceProvider {
    public function register(): void {
        ResolveEnumMacro::register();
    }
}
