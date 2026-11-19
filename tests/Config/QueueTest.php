<?php

declare(strict_types=1);

use function Safe\file_get_contents;
use function Safe\preg_match;

function readWorkerTimeoutInSeconds(): int {
    preg_match('/queue:work[^\n]*--timeout=(\d+)/', file_get_contents(base_path('docker/configs/laravel-worker-supervisor.conf')), $matches);

    return (int) $matches[1];
}

describe('queue retry window', function (): void {
    test('is longer than the worker timeout for every connection that releases jobs', function (string $connection): void {
        $retry_after = config()->integer("queue.connections.{$connection}.retry_after");

        expect($retry_after)->toBeGreaterThan(readWorkerTimeoutInSeconds());
    })->with(['database', 'beanstalkd', 'redis']);

    test('detects a worker timeout the retry window does not cover', function (): void {
        expect(readWorkerTimeoutInSeconds())->toBe(600)
            ->and(90)->not->toBeGreaterThan(readWorkerTimeoutInSeconds());
    });
});
