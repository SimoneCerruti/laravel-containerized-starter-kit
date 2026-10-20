<?php

declare(strict_types=1);

namespace App\Providers;

use App\Macros\Number\FromMinorUnitsMacro;
use App\Macros\Number\ToMinorUnitsMacro;
use Illuminate\Support\ServiceProvider;

final class NumberMacroServiceProvider extends ServiceProvider {
    public function register(): void {
        FromMinorUnitsMacro::register();
        ToMinorUnitsMacro::register();
    }
}
