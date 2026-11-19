<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use function Safe\file_get_contents;

describe('query logger', function (): void {
    test('is enabled with a 500ms threshold and a dedicated channel by default', function (): void {
        expect(config('logging.query.enabled'))->toBeTrue()
            ->and(config('logging.query.slower_than'))->toBe(500)
            ->and(config('logging.query.channel'))->toBe('slow_queries')
            ->and(config('logging.channels.slow_queries.driver'))->toBe('daily');
    });

    test('lists its enable flag in the example environment file', function (): void {
        expect(file_get_contents(base_path('.env.example')))->toContain("DB_SLOW_QUERY_LOG_ENABLED=true\n");
    });

    test('logs a query slower than the threshold on the dedicated channel', function (): void {
        config()->set('logging.query.slower_than', 0);

        Log::shouldReceive('channel')->once()->with('slow_queries')->andReturnSelf();
        Log::shouldReceive('debug')->once()
            ->withArgs(fn (string $message): bool => str_contains($message, 'select 1 as slow_probe'));

        DB::select('select 1 as slow_probe');
    });

    test('does not log a query faster than the threshold', function (): void {
        config()->set('logging.query.slower_than', 60_000);

        Log::shouldReceive('channel')->never();

        DB::select('select 1 as fast_probe');
    });

    test('does not log a statement matching an excluded pattern', function (): void {
        config()->set('logging.query.slower_than', 0);
        config()->set('logging.query.except', ['select 1 as ignored_probe']);

        Log::shouldReceive('channel')->never();

        DB::select('select 1 as ignored_probe');
    });
});
