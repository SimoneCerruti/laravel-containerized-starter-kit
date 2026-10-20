<?php

declare(strict_types=1);

namespace App\Providers;

use App\Macros\Str\EscapeControlCharactersMacro;
use Illuminate\Support\ServiceProvider;

final class StrMacroServiceProvider extends ServiceProvider {
    public function register(): void {
        EscapeControlCharactersMacro::register();
    }
}
