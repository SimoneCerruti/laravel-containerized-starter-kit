<?php

declare(strict_types=1);

use Database\Seeders\Concerns\RunsOnlyInLocalEnvironment;
use Illuminate\Support\Facades\App;

interface GuardedSeederStub {
    public function guard(): void;
}

function guardedSeederStub(): GuardedSeederStub {
    return new class implements GuardedSeederStub {
        use RunsOnlyInLocalEnvironment;

        public function guard(): void {
            $this->assertLocalEnvironment();
        }
    };
}

describe('assertLocalEnvironment', function (): void {
    test('allows the testing environment the test suite itself runs under', function (): void {
        expect(App::environment('testing'))->toBeTrue();

        guardedSeederStub()->guard();
    });

    test('allows the local environment', function (): void {
        App::detectEnvironment(fn (): string => 'local');

        expect(App::environment('local'))->toBeTrue();

        guardedSeederStub()->guard();
    });

    test('blocks the production environment with a message naming it', function (): void {
        App::detectEnvironment(fn (): string => 'production');

        expect(App::environment('production'))->toBeTrue()
            ->and(fn () => guardedSeederStub()->guard())
            ->toThrow(RuntimeException::class, 'must not run outside local/testing — current environment is [production]');
    });

    test('blocks the staging environment with a message naming it', function (): void {
        App::detectEnvironment(fn (): string => 'staging');

        expect(fn () => guardedSeederStub()->guard())
            ->toThrow(RuntimeException::class, 'current environment is [staging]');
    });
});
