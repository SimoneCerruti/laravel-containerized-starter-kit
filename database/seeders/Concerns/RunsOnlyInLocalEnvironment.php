<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Guards a demo seeder against running anywhere the data it generates
 * would be nonsensical or dangerous — everywhere except local development
 * and the test suite (`phpunit.xml` runs with `APP_ENV=testing`).
 */
trait RunsOnlyInLocalEnvironment {
    protected function assertLocalEnvironment(): void {
        /**
         * @var list<string>
         */
        $allowed_environments = config()->array('database.seeders_allowed_environments');

        throw_unless(App::environment($allowed_environments), RuntimeException::class, \sprintf(
            '%s seeds fake demo data and must not run outside %s — current environment is [%s].',
            static::class,
            implode('/', $allowed_environments),
            Str::escapeControlCharacters(App::environment()),
        ));
    }
}
